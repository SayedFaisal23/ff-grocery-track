<?php

namespace App\Http\Controllers;

use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TuntutanController extends Controller
{
    /**
     * Paparkan senarai permohonan mengikut minggu.
     */
    public function index()
    {
        $user = Auth::user();
        $query = Tuntutan::query()->with(['user', 'reviewer']);

        if ($user->hasRole('Stocker')) {
            $query->where('user_id', $user->id);
        }

        $claims = $query->orderByDesc('tarikh_beli')->get();
        $claimsGrouped = $claims->groupBy('minggu_tuntutan');

        return view('tuntutan.index', compact('claimsGrouped'));
    }

    /**
     * Tunjukkan borang permohonan pembelian.
     */
    public function create()
    {
        if (! Auth::user()->hasRole('Stocker')) {
            abort(403, 'Hanya Stocker sahaja dibenarkan membuat permohonan.');
        }

        $platforms = $this->presetsFor(TuntutanPreset::TYPE_PURCHASE_PLATFORM);
        $paymentMethods = $this->presetsFor(TuntutanPreset::TYPE_PAYMENT_METHOD);

        return view('tuntutan.create', compact('platforms', 'paymentMethods'));
    }

    /**
     * Paparkan lampiran tuntutan melalui aplikasi, tanpa bergantung pada
     * pelayan web untuk membenarkan akses kepada symbolic link /storage.
     */
    public function showAttachment(Tuntutan $tuntutan)
    {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin') && $tuntutan->user_id !== $user->id) {
            abort(403);
        }

        $attachmentPath = $tuntutan->attachment;

        if (
            ! is_string($attachmentPath)
            || ! str_starts_with($attachmentPath, 'attachments/')
            || str_contains($attachmentPath, '..')
            || ! Storage::disk('public')->exists($attachmentPath)
        ) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachmentPath,
            basename($attachmentPath),
            ['X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }

    /**
     * Simpan lampiran selepas permohonan Pantry/General diluluskan.
     */
    public function uploadAttachment(Request $request, Tuntutan $tuntutan)
    {
        $user = Auth::user();

        if ($tuntutan->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $result = DB::transaction(function () use ($request, $tuntutan) {
            $claim = Tuntutan::query()->lockForUpdate()->findOrFail($tuntutan->id);

            if (! $claim->canUploadAttachment()) {
                return null;
            }

            $oldData = $claim->toArray();
            $claim->update([
                'attachment' => $request->file('attachment')->store('attachments', 'public'),
                'status' => 'Completed',
            ]);

            return [$oldData, $claim];
        });

        if ($result === null) {
            return back()->with('error', 'Lampiran hanya boleh dimuat naik sekali selepas permohonan diluluskan.');
        }

        [$oldData, $claim] = $result;

        LogAktiviti::create([
            'user_id' => $user->id,
            'aktiviti' => "{$user->name} telah memuat naik lampiran dan melengkapkan permohonan ID {$claim->id} ({$claim->nama_item}).",
            'item_id' => null,
            'data_lama' => $oldData,
            'data_baru' => $claim->toArray(),
        ]);

        return back()->with('success', 'Lampiran berjaya dimuat naik. Permohonan telah lengkap.');
    }

    /**
     * Simpan permohonan pembelian atau tuntutan Lunch mingguan.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasRole('Stocker')) {
            abort(403);
        }

        $tag = $request->input('tag');
        if (! in_array($tag, ['Pantry', 'General', 'Lunch'], true)) {
            return back()->withInput()->withErrors(['tag' => 'Jenis permohonan tidak sah.']);
        }

        if ($tag === 'Lunch') {
            $this->storeLunchClaims($request, $user->id, $user->name);
        } else {
            $this->storePurchaseRequest($request, $user->id, $user->name, $tag);
        }

        return redirect()->route('tuntutan.index')->with('success', 'Permohonan berjaya dihantar.');
    }

    /**
     * Rekodkan keputusan Superadmin untuk permohonan yang belum disemak.
     */
    public function updateStatus(Request $request, Tuntutan $tuntutan)
    {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin')) {
            abort(403, 'Hanya Superadmin sahaja dibenarkan menguruskan status permohonan.');
        }

        $validated = $request->validate([
            'approval_result' => ['required', Rule::in(['Approved', 'Rejected'])],
        ]);

        $result = DB::transaction(function () use ($tuntutan, $user, $validated) {
            $claim = Tuntutan::query()->lockForUpdate()->findOrFail($tuntutan->id);

            if ($claim->status !== 'Pending' || $claim->approval_result !== null) {
                return null;
            }

            $oldData = $claim->toArray();
            $isApprovedPurchaseRequest = $validated['approval_result'] === 'Approved'
                && $claim->isPurchaseRequest();
            $isHistoricalRequestWithAttachment = $isApprovedPurchaseRequest && $claim->attachment !== null;

            $claim->update([
                'status' => $isApprovedPurchaseRequest && ! $isHistoricalRequestWithAttachment
                    ? 'Pending'
                    : 'Completed',
                'approval_result' => $validated['approval_result'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            return [$oldData, $claim];
        });

        if ($result === null) {
            return back()->with('error', 'Permohonan ini telah disemak dan tidak boleh dikemaskini lagi.');
        }

        [$oldData, $claim] = $result;
        $decision = $claim->approval_result === 'Approved' ? 'meluluskan' : 'menolak';

        LogAktiviti::create([
            'user_id' => $user->id,
            'aktiviti' => "{$user->name} telah {$decision} permohonan ID {$claim->id} ({$claim->nama_item}).",
            'item_id' => null,
            'data_lama' => $oldData,
            'data_baru' => $claim->toArray(),
        ]);

        $message = $claim->status === 'Pending'
            ? 'Permohonan diluluskan. Pemohon boleh memuat naik lampiran.'
            : 'Keputusan permohonan berjaya direkodkan.';

        return back()->with('success', $message);
    }

    private function storePurchaseRequest(Request $request, int $userId, string $requestorName, string $tag): void
    {
        $isOtherPaymentMethod = $request->input('payment_method') === Tuntutan::OTHER_PAYMENT_METHOD;
        $paymentMethodRules = ['required', 'string', 'max:255'];
        $otherPaymentMethodRules = ['nullable', 'string', 'max:255'];

        if ($isOtherPaymentMethod) {
            $paymentMethodRules[] = Rule::in([Tuntutan::OTHER_PAYMENT_METHOD]);
            $otherPaymentMethodRules[] = 'required';
        } else {
            $paymentMethodRules[] = Rule::exists('tuntutan_presets', 'name')
                ->where('type', TuntutanPreset::TYPE_PAYMENT_METHOD);
            $otherPaymentMethodRules[] = 'prohibited';
        }

        $validated = $request->validate([
            'tag' => ['required', Rule::in(['Pantry', 'General'])],
            'request_date' => ['required', 'date', 'before_or_equal:today'],
            'item_specification' => ['required', 'string', 'max:255'],
            'purchase_purpose' => ['required', 'string', 'max:1000'],
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'purchase_platform' => [
                'required',
                'string',
                'max:255',
                Rule::exists('tuntutan_presets', 'name')
                    ->where('type', TuntutanPreset::TYPE_PURCHASE_PLATFORM),
            ],
            'total_item_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => $paymentMethodRules,
            'other_payment_method' => $otherPaymentMethodRules,
            'invoice_sent_to_account' => ['required', 'boolean'],
            'date_receive' => ['required', 'date', 'after_or_equal:request_date'],
            'attachment' => ['prohibited'],
        ], [
            'purchase_platform.exists' => 'Sila pilih platform pembelian yang telah ditetapkan oleh Superadmin.',
            'payment_method.exists' => 'Please select a payment method configured by the Superadmin.',
            'other_payment_method.required' => 'Please specify the other payment method.',
            'date_receive.after_or_equal' => 'Tarikh terima tidak boleh sebelum tarikh permohonan.',
        ]);

        $requestDate = Carbon::parse($validated['request_date']);
        $claim = Tuntutan::create([
            'user_id' => $userId,
            'requestor_name' => $requestorName,
            'nama_item' => $validated['item_specification'],
            'item_specification' => $validated['item_specification'],
            'purchase_purpose' => $validated['purchase_purpose'],
            'invoice_no' => $validated['invoice_no'] ?? null,
            'purchase_platform' => $validated['purchase_platform'],
            'tag' => $tag,
            'nilai_tuntutan' => $validated['total_item_amount'],
            'total_item_amount' => $validated['total_item_amount'],
            'payment_method' => $validated['payment_method'],
            'other_payment_method' => $isOtherPaymentMethod ? trim($validated['other_payment_method']) : null,
            'invoice_sent_to_account' => $validated['invoice_sent_to_account'],
            'request_date' => $validated['request_date'],
            'date_receive' => $validated['date_receive'],
            'tarikh_beli' => $validated['request_date'],
            'minggu_tuntutan' => $this->weekFor($requestDate),
            'status' => 'Pending',
        ]);

        LogAktiviti::create([
            'user_id' => $userId,
            'aktiviti' => "Menghantar permohonan pembelian {$claim->tag}: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
            'item_id' => null,
            'data_baru' => $claim->toArray(),
        ]);
    }

    private function storeLunchClaims(Request $request, int $userId, string $requestorName): void
    {
        $request->validate([
            'week' => ['required', 'regex:/^\d{4}-W\d{2}$/'],
            'lunch_dates' => ['required', 'array', 'size:7'],
            'lunch_dates.*' => ['required', 'date'],
            'lunch_butirans' => ['required', 'array', 'size:7'],
            'lunch_pax' => ['required', 'array', 'size:7'],
            'lunch_pax.*' => ['nullable', 'integer', 'min:0'],
            'lunch_hargas' => ['required', 'array', 'size:7'],
            'lunch_hargas.*' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $lunchDates = $request->input('lunch_dates');
        $lunchButirans = $request->input('lunch_butirans');
        $lunchPaxes = $request->input('lunch_pax');
        $lunchHargas = $request->input('lunch_hargas');
        $week = $request->input('week');
        $hasClaim = false;

        for ($i = 0; $i < 7; $i++) {
            $pax = (int) ($lunchPaxes[$i] ?? 0);
            if ($pax === 0) {
                continue;
            }

            $hasClaim = true;
            $date = Carbon::parse($lunchDates[$i]);
            $butiran = trim((string) ($lunchButirans[$i] ?? ''));
            $harga = (float) ($lunchHargas[$i] ?? 0);

            if ($date->isFuture()) {
                throw new HttpResponseException(
                    back()->withInput()->withErrors(['lunch_dates' => 'Tarikh tuntutan tidak boleh pada masa hadapan.'])
                );
            }

            if ($butiran === '') {
                throw new HttpResponseException(
                    back()->withInput()->withErrors(['lunch_butirans' => 'Butiran lunch tidak boleh dikosongkan bagi hari yang dituntut.'])
                );
            }

            if ($harga <= 0) {
                throw new HttpResponseException(
                    back()->withInput()->withErrors(['lunch_hargas' => 'Sila masukkan harga per pax yang sah untuk hari yang dituntut.'])
                );
            }
        }

        if (! $hasClaim) {
            throw new HttpResponseException(
                back()->withInput()->withErrors(['lunch_pax' => 'Sila tuntut sekurang-kurangnya untuk satu hari.'])
            );
        }

        $attachmentPath = $this->storeAttachment($request);

        DB::transaction(function () use ($lunchDates, $lunchButirans, $lunchPaxes, $lunchHargas, $week, $attachmentPath, $userId, $requestorName): void {
            for ($i = 0; $i < 7; $i++) {
                $pax = (int) ($lunchPaxes[$i] ?? 0);
                if ($pax === 0) {
                    continue;
                }

                $butiran = trim((string) $lunchButirans[$i]);
                $harga = (float) $lunchHargas[$i];
                $nilai = $pax * $harga;
                $namaItem = "{$butiran} ({$pax} pax @ RM ".number_format($harga, 2).'/pax)';

                $claim = Tuntutan::create([
                    'user_id' => $userId,
                    'requestor_name' => $requestorName,
                    'request_date' => now()->toDateString(),
                    'nama_item' => $namaItem,
                    'item_specification' => $butiran,
                    'tag' => 'Lunch',
                    'nilai_tuntutan' => $nilai,
                    'total_item_amount' => $nilai,
                    'tarikh_beli' => $lunchDates[$i],
                    'minggu_tuntutan' => $week,
                    'status' => 'Pending',
                    'attachment' => $attachmentPath,
                ]);

                LogAktiviti::create([
                    'user_id' => $userId,
                    'aktiviti' => "Menghantar tuntutan lunch: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
                    'item_id' => null,
                    'data_baru' => $claim->toArray(),
                ]);
            }
        });
    }

    private function storeAttachment(Request $request): ?string
    {
        return $request->hasFile('attachment')
            ? $request->file('attachment')->store('attachments', 'public')
            : null;
    }

    private function presetsFor(string $type)
    {
        return TuntutanPreset::query()
            ->forType($type)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function weekFor(Carbon $date): string
    {
        return $date->format('o').'-W'.sprintf('%02d', $date->weekOfYear);
    }
}
