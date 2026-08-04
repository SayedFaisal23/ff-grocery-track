<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventori;
use App\Models\Kategori;
use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    /**
     * Log masuk & jana api_token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = Str::random(60);
            $user->update(['api_token' => $token]);

            LogAktiviti::create([
                'user_id' => $user->id,
                'aktiviti' => 'Pengguna berjaya log masuk ke dalam sistem melalui Android API.',
            ]);

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
                ],
            ]);
        }

        return response()->json([
            'message' => 'Maklumat log masuk yang diberikan tidak sepadan dengan rekod kami.',
        ], 422);
    }

    /**
     * Log keluar & padam token.
     */
    public function logout()
    {
        $user = Auth::user();
        if ($user) {
            LogAktiviti::create([
                'user_id' => $user->id,
                'aktiviti' => 'Pengguna berjaya log keluar dari sistem melalui Android API.',
            ]);
            $user->update(['api_token' => null]);
        }

        return response()->json(['message' => 'Berjaya log keluar.']);
    }

    /**
     * Dapatkan maklumat pengguna semasa.
     */
    public function user()
    {
        $user = Auth::user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
        ]);
    }

    /**
     * Senarai Inventori.
     */
    public function inventoriList(Request $request)
    {
        $query = Inventori::with('kategoriPreset');

        if ($request->filled('carian')) {
            $query->where('nama_item', 'like', '%'.$request->carian.'%');
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->kategori_id);
        } elseif ($request->filled('kategori')) {
            $query->whereHas('kategoriPreset', function ($categoryQuery) use ($request) {
                $categoryQuery->where('nama', $request->kategori);
            });
        }

        $items = $query->orderBy('nama_item', 'asc')->get();
        $categories = Kategori::orderBy('nama')->get();

        return response()->json([
            'items' => $items,
            'kategoriSenarai' => $categories->pluck('nama'),
            'categories' => $categories,
        ]);
    }

    public function kategoriList()
    {
        return response()->json(
            Kategori::orderBy('nama')->get()
        );
    }

    /**
     * Senarai Perlu Restok.
     */
    public function restokList()
    {
        $habisStok = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', 0)
            ->orderBy('nama_item')
            ->get();

        $bawahAmbang = Inventori::with('kategoriPreset')
            ->where('jumlah_belum_dibuka', '>', 0)
            ->whereColumn('jumlah_belum_dibuka', '<=', 'had_ambang')
            ->orderBy('nama_item')
            ->get();

        return response()->json([
            'habisStok' => $habisStok,
            'bawahAmbang' => $bawahAmbang,
        ]);
    }

    /**
     * Tambah Barang.
     */
    public function inventoriStore(Request $request)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $this->resolveKategoriId($request);

        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ]);

        $validated['jejak_luput'] = $request->input('jejak_luput', false);
        $validated['dicipta_oleh'] = Auth::id();
        $validated['dikemaskini_oleh'] = Auth::id();

        $item = Inventori::create($validated);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah item baharu melalui API: {$item->nama_item}.",
            'item_id' => $item->id,
            'data_baru' => $item->toArray(),
        ]);

        return response()->json($item, 201);
    }

    /**
     * Kemaskini Barang.
     */
    public function inventoriUpdate(Request $request, Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $this->resolveKategoriId($request);

        $validated = $request->validate([
            'nama_item' => 'required|string|max:255',
            'kategori_id' => 'required|integer|exists:categories,id',
            'jenis' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
            'tarikh_luput' => 'nullable|date',
            'jejak_luput' => 'nullable|boolean',
            'had_ambang' => 'required|integer|min:0',
        ]);

        $validated['jejak_luput'] = $request->input('jejak_luput', false);
        $validated['dikemaskini_oleh'] = Auth::id();

        $oldData = $inventori->toArray();
        $oldBelumDibuka = $inventori->jumlah_belum_dibuka;

        $inventori->update($validated);

        if ($inventori->jumlah_belum_dibuka === 0 && $oldBelumDibuka > 0) {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Baki item mencapai kosong: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        } else {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Mengemaskini maklumat stok bagi item melalui API: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return response()->json($inventori);
    }

    /**
     * Pelarasan Kuantiti Pantas.
     */
    public function inventoriAdjust(Request $request, Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'jumlah_belum_dibuka' => 'required|integer|min:0',
            'peratus_baki' => 'required|integer|between:0,100',
        ]);

        $oldData = $inventori->toArray();
        $oldBelumDibuka = $inventori->jumlah_belum_dibuka;

        $inventori->update([
            'jumlah_belum_dibuka' => $request->jumlah_belum_dibuka,
            'peratus_baki' => $request->peratus_baki,
            'dikemaskini_oleh' => Auth::id(),
        ]);

        if ($inventori->jumlah_belum_dibuka === 0 && $oldBelumDibuka > 0) {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Baki item mencapai kosong: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        } else {
            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Melaraskan kuantiti/peratus baki bagi item melalui API: {$inventori->nama_item}.",
                'item_id' => $inventori->id,
                'data_lama' => $oldData,
                'data_baru' => $inventori->toArray(),
            ]);
        }

        return response()->json($inventori);
    }

    /**
     * Padam Barang.
     */
    public function inventoriDestroy(Inventori $inventori)
    {
        if (! Auth::user()->hasAnyRole(['Superadmin', 'Stocker', 'Tracker'])) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $oldData = $inventori->toArray();
        $itemName = $inventori->nama_item;

        $inventori->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam item inventori melalui API: {$itemName}.",
            'item_id' => null,
            'data_lama' => $oldData,
        ]);

        return response()->json(['message' => 'Barang berjaya dipadam.']);
    }

    /**
     * Senarai Tuntutan.
     */
    public function tuntutanList()
    {
        $user = Auth::user();
        if (! $user->hasAnyRole(['Superadmin', 'Stocker'])) {
            return response()->json(['message' => 'Tiada kebenaran untuk melihat permohonan pembelian.'], 403);
        }

        $query = Tuntutan::with(['user', 'reviewer']);

        if ($user->hasRole('Stocker')) {
            $query->where('user_id', $user->id);
        }

        $claims = $query->orderBy('tarikh_beli', 'desc')->get();

        return response()->json($claims);
    }

    /**
     * Senarai pilihan tetap untuk borang permohonan pembelian.
     */
    public function tuntutanPresetList()
    {
        $user = Auth::user();
        if (! $user->hasAnyRole(['Superadmin', 'Stocker'])) {
            return response()->json(['message' => 'Tiada kebenaran untuk melihat pilihan permohonan.'], 403);
        }

        return response()->json([
            'purchase_platforms' => $this->apiPresetsFor(TuntutanPreset::TYPE_PURCHASE_PLATFORM),
            'payment_methods' => $this->apiPresetsFor(TuntutanPreset::TYPE_PAYMENT_METHOD),
        ]);
    }

    /**
     * Hantar Tuntutan.
     */
    public function tuntutanStore(Request $request)
    {
        if (! Auth::user()->hasRole('Stocker')) {
            return response()->json(['message' => 'Hanya Stocker sahaja dibenarkan membuat tuntutan.'], 403);
        }

        $tag = $request->input('tag');
        if (! in_array($tag, ['Pantry', 'General', 'Lunch'], true)) {
            return response()->json(['message' => 'Jenis permohonan tidak sah.'], 422);
        }

        if ($tag === 'Pantry' || $tag === 'General') {
            $isOtherPaymentMethod = $request->input('payment_method') === Tuntutan::OTHER_PAYMENT_METHOD;
            $paymentMethodRules = ['required', 'string', 'max:255'];
            $otherPaymentMethodRules = ['nullable', 'string', 'max:255'];

            if ($isOtherPaymentMethod) {
                $paymentMethodRules[] = Rule::in([Tuntutan::OTHER_PAYMENT_METHOD]);
                $otherPaymentMethodRules[] = Rule::in([Tuntutan::OTHER_PAYMENT_METHOD_DETAIL]);
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
                    'required', 'string', 'max:255',
                    Rule::exists('tuntutan_presets', 'name')->where('type', TuntutanPreset::TYPE_PURCHASE_PLATFORM),
                ],
                'total_item_amount' => ['required', 'numeric', 'min:0.01'],
                'payment_method' => $paymentMethodRules,
                'other_payment_method' => $otherPaymentMethodRules,
                'invoice_sent_to_account' => ['required', 'boolean'],
                'date_receive' => ['required', 'date', 'after_or_equal:request_date'],
                'attachment' => ['prohibited'],
            ]);

            $date = Carbon::parse($validated['request_date']);

            $claim = Tuntutan::create([
                'user_id' => Auth::id(),
                'requestor_name' => Auth::user()->name,
                'nama_item' => $validated['item_specification'],
                'item_specification' => $validated['item_specification'],
                'purchase_purpose' => $validated['purchase_purpose'],
                'invoice_no' => $validated['invoice_no'] ?? null,
                'purchase_platform' => $validated['purchase_platform'],
                'tag' => $tag,
                'nilai_tuntutan' => $validated['total_item_amount'],
                'total_item_amount' => $validated['total_item_amount'],
                'payment_method' => $validated['payment_method'],
                'other_payment_method' => $isOtherPaymentMethod ? Tuntutan::OTHER_PAYMENT_METHOD_DETAIL : null,
                'invoice_sent_to_account' => $validated['invoice_sent_to_account'],
                'request_date' => $validated['request_date'],
                'date_receive' => $validated['date_receive'],
                'tarikh_beli' => $validated['request_date'],
                'minggu_tuntutan' => $this->weekFor($date),
                'status' => 'Pending',
            ]);

            LogAktiviti::create([
                'user_id' => Auth::id(),
                'aktiviti' => "Menghantar permohonan pembelian melalui API: {$claim->nama_item} [{$claim->tag}] bernilai RM{$claim->nilai_tuntutan}.",
                'data_baru' => $claim->toArray(),
            ]);

            return response()->json($claim, 201);
        }

        return $this->storeApiLunchClaims($request);
    }

    /**
     * Kemaskini Status Tuntutan.
     */
    public function tuntutanUpdateStatus(Request $request, Tuntutan $tuntutan)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Hanya Superadmin dibenarkan mengurus status tuntutan.'], 403);
        }

        $validated = $request->validate([
            'approval_result' => ['required', Rule::in(['Approved', 'Rejected'])],
        ]);

        $result = DB::transaction(function () use ($tuntutan, $validated) {
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
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return [$oldData, $claim];
        });

        if ($result === null) {
            return response()->json(['message' => 'Permohonan ini telah disemak dan tidak boleh dikemaskini lagi.'], 409);
        }

        [$oldData, $claim] = $result;

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Merekod keputusan {$claim->approval_result} bagi permohonan ID {$claim->id} ({$claim->nama_item}) melalui API.",
            'data_lama' => $oldData,
            'data_baru' => $claim->toArray(),
        ]);

        return response()->json($claim);
    }

    /**
     * Muat naik lampiran selepas permohonan Pantry/General diluluskan.
     */
    public function tuntutanUploadAttachment(Request $request, Tuntutan $tuntutan)
    {
        $user = Auth::user();

        if (! $user->hasRole('Stocker') || $tuntutan->user_id !== $user->id) {
            return response()->json(['message' => 'Hanya pemohon dibenarkan memuat naik lampiran.'], 403);
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
            return response()->json([
                'message' => 'Lampiran hanya boleh dimuat naik sekali selepas permohonan diluluskan.',
            ], 409);
        }

        [$oldData, $claim] = $result;

        LogAktiviti::create([
            'user_id' => $user->id,
            'aktiviti' => "{$user->name} telah memuat naik lampiran dan melengkapkan permohonan ID {$claim->id} ({$claim->nama_item}) melalui API.",
            'data_lama' => $oldData,
            'data_baru' => $claim->toArray(),
        ]);

        return response()->json($claim);
    }

    /**
     * Tambah pilihan tetap (Superadmin sahaja).
     */
    public function tuntutanPresetStore(Request $request)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Hanya Superadmin dibenarkan mengurus pilihan permohonan.'], 403);
        }

        $validated = $this->validateApiPreset($request);
        $preset = DB::transaction(function () use ($validated) {
            $lastSortOrder = TuntutanPreset::query()
                ->forType($validated['type'])
                ->orderByDesc('sort_order')
                ->lockForUpdate()
                ->value('sort_order');

            return TuntutanPreset::create(array_merge($validated, ['sort_order' => ((int) $lastSortOrder) + 1]));
        });

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Menambah pilihan tuntutan {$preset->name} melalui API.",
            'data_baru' => $preset->toArray(),
        ]);

        return response()->json($preset, 201);
    }

    public function tuntutanPresetUpdate(Request $request, TuntutanPreset $tuntutanPreset)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Hanya Superadmin dibenarkan mengurus pilihan permohonan.'], 403);
        }

        $validated = $this->validateApiPreset($request, $tuntutanPreset);
        $oldData = $tuntutanPreset->toArray();
        $tuntutanPreset->update([
            'name' => $validated['name'],
            'sort_order' => $request->integer('sort_order', $tuntutanPreset->sort_order),
        ]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini pilihan tuntutan {$tuntutanPreset->name} melalui API.",
            'data_lama' => $oldData,
            'data_baru' => $tuntutanPreset->toArray(),
        ]);

        return response()->json($tuntutanPreset);
    }

    public function tuntutanPresetDestroy(TuntutanPreset $tuntutanPreset)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Hanya Superadmin dibenarkan mengurus pilihan permohonan.'], 403);
        }

        $oldData = $tuntutanPreset->toArray();
        $name = $tuntutanPreset->name;
        $tuntutanPreset->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam pilihan tuntutan {$name} melalui API.",
            'data_lama' => $oldData,
        ]);

        return response()->json(['message' => 'Pilihan berjaya dipadam.']);
    }

    private function storeApiLunchClaims(Request $request)
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

        $dates = $request->input('lunch_dates');
        $butirans = $request->input('lunch_butirans');
        $paxes = $request->input('lunch_pax');
        $prices = $request->input('lunch_hargas');
        $errors = [];
        $hasClaim = false;

        for ($i = 0; $i < 7; $i++) {
            $pax = (int) ($paxes[$i] ?? 0);
            if ($pax === 0) {
                continue;
            }

            $hasClaim = true;
            if (Carbon::parse($dates[$i])->isFuture()) {
                $errors['lunch_dates'] = 'Tarikh tuntutan tidak boleh pada masa hadapan.';
            }
            if (trim((string) ($butirans[$i] ?? '')) === '') {
                $errors['lunch_butirans'] = 'Butiran lunch tidak boleh dikosongkan bagi hari yang dituntut.';
            }
            if ((float) ($prices[$i] ?? 0) <= 0) {
                $errors['lunch_hargas'] = 'Sila masukkan harga per pax yang sah untuk hari yang dituntut.';
            }
        }

        if (! $hasClaim) {
            $errors['lunch_pax'] = 'Sila tuntut sekurang-kurangnya untuk satu hari.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('attachments', 'public')
            : null;
        $week = $request->input('week');
        $user = Auth::user();

        $createdClaims = DB::transaction(function () use ($dates, $butirans, $paxes, $prices, $attachmentPath, $week, $user) {
            $claims = [];

            for ($i = 0; $i < 7; $i++) {
                $pax = (int) ($paxes[$i] ?? 0);
                if ($pax === 0) {
                    continue;
                }

                $butiran = trim((string) $butirans[$i]);
                $price = (float) $prices[$i];
                $amount = $pax * $price;
                $itemName = "{$butiran} ({$pax} pax @ RM ".number_format($price, 2).'/pax)';

                $claim = Tuntutan::create([
                    'user_id' => $user->id,
                    'requestor_name' => $user->name,
                    'request_date' => now()->toDateString(),
                    'nama_item' => $itemName,
                    'item_specification' => $butiran,
                    'tag' => 'Lunch',
                    'nilai_tuntutan' => $amount,
                    'total_item_amount' => $amount,
                    'tarikh_beli' => $dates[$i],
                    'minggu_tuntutan' => $week,
                    'status' => 'Pending',
                    'attachment' => $attachmentPath,
                ]);

                LogAktiviti::create([
                    'user_id' => $user->id,
                    'aktiviti' => "Menghantar tuntutan lunch melalui API: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
                    'data_baru' => $claim->toArray(),
                ]);

                $claims[] = $claim;
            }

            return $claims;
        });

        return response()->json([
            'message' => 'Tuntutan lunch berjaya dihantar.',
            'claims' => $createdClaims,
        ], 201);
    }

    private function apiPresetsFor(string $type)
    {
        return TuntutanPreset::query()
            ->forType($type)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);
    }

    private function validateApiPreset(Request $request, ?TuntutanPreset $preset = null): array
    {
        $request->merge([
            'type' => $preset?->type ?? (is_string($request->input('type')) ? trim($request->input('type')) : $request->input('type')),
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
        ]);

        return $request->validate([
            'type' => ['required', Rule::in(TuntutanPreset::types())],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tuntutan_presets', 'name')
                    ->where(fn ($query) => $query->where('type', $request->input('type')))
                    ->ignore($preset),
                Rule::when(
                    $request->input('type') === TuntutanPreset::TYPE_PAYMENT_METHOD,
                    Rule::notIn([Tuntutan::OTHER_PAYMENT_METHOD])
                ),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function weekFor(Carbon $date): string
    {
        return $date->format('o').'-W'.sprintf('%02d', $date->weekOfYear);
    }

    /**
     * Senarai Pengguna (Superadmin sahaja).
     */
    public function penggunaList()
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $users = User::with('roles')->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'Tiada Peranan',
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json($users);
    }

    /**
     * Tambah Pengguna.
     */
    public function penggunaStore(Request $request)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:Superadmin,Stocker,Tracker',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mendaftar pengguna baharu: {$user->name} dengan peranan {$request->role} melalui API.",
            'data_baru' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->role,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ], 201);
    }

    /**
     * Kemaskini Pengguna.
     */
    public function penggunaUpdate(Request $request, User $user)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|in:Superadmin,Stocker,Tracker',
        ]);

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        $user->syncRoles([$request->role]);

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Mengemaskini maklumat pengguna: {$user->name} melalui API.",
            'data_lama' => $oldData,
            'data_baru' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ],
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->role,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Padam Pengguna.
     */
    public function penggunaDestroy(User $user)
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'Anda tidak boleh memadam akaun anda sendiri.'], 400);
        }

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
        ];

        $userName = $user->name;
        $user->delete();

        LogAktiviti::create([
            'user_id' => Auth::id(),
            'aktiviti' => "Memadam pengguna: {$userName} melalui API.",
            'data_lama' => $oldData,
        ]);

        return response()->json(['message' => 'Pengguna berjaya dipadam.']);
    }

    /**
     * Senarai Log Aktiviti (Superadmin sahaja).
     */
    public function logAktivitiList()
    {
        if (! Auth::user()->hasRole('Superadmin')) {
            return response()->json(['message' => 'Tiada kebenaran.'], 403);
        }

        $logs = LogAktiviti::with('user.roles')
            ->orderBy('created_at', 'desc')
            ->take(150)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'user_name' => $log->user?->name ?? 'Sistem / Pengguna Dipadam',
                    'user_role' => $log->user?->roles->first()?->name ?? 'Tiada Peranan',
                    'aktiviti' => $log->aktiviti,
                    'data_lama' => $log->data_lama,
                    'data_baru' => $log->data_baru,
                ];
            });

        return response()->json($logs);
    }

    private function resolveKategoriId(Request $request): void
    {
        if ($request->filled('kategori_id') || ! $request->filled('kategori')) {
            return;
        }

        $categoryId = Kategori::where('nama', $request->input('kategori'))->value('id');

        if ($categoryId) {
            $request->merge(['kategori_id' => $categoryId]);
        }
    }
}
