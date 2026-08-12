<?php

namespace App\Http\Controllers;

use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use App\Services\ClaimDocumentService;
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
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Tuntutan::query()->with(['user', 'reviewer', 'receiptViewer']);
        $selectedWeeks = $this->selectedWeeks($request);
        $calendarMonth = $this->calendarMonth($request);
        $selectedType = $this->selectedType($request);
        $selectedStatus = $this->selectedStatus($request);

        if ($user->hasRole('Stocker')) {
            $query->where('user_id', $user->id);
        }

        if ($selectedWeeks !== []) {
            $query->whereIn('minggu_tuntutan', $selectedWeeks);
        }

        if ($selectedType !== null) {
            $query->where('tag', $selectedType);
        }

        if ($selectedStatus !== null) {
            $query->withWorkflowStatus($selectedStatus);
        }

        $claims = $query
            ->orderByDesc('tarikh_beli')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $claimsGrouped = $claims->groupBy('minggu_tuntutan');
        $calendarWeeks = $this->calendarWeeks($calendarMonth);

        return view('tuntutan.index', compact(
            'calendarMonth',
            'calendarWeeks',
            'claimsGrouped',
            'selectedWeeks',
            'selectedType',
            'selectedStatus',
        ));
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
    public function showAttachment(Tuntutan $tuntutan, ClaimDocumentService $claimDocuments)
    {
        return $this->showDocument(
            $tuntutan,
            Tuntutan::DOCUMENT_ATTACHMENT,
            $claimDocuments,
        );
    }

    /**
     * Serve the optional quotation/invoice submitted with a Pantry/General
     * purchase request through the same authorised document flow.
     */
    public function showPurchaseAttachment(Tuntutan $tuntutan, ClaimDocumentService $claimDocuments)
    {
        return $this->showDocument(
            $tuntutan,
            Tuntutan::DOCUMENT_PURCHASE_ATTACHMENT,
            $claimDocuments,
        );
    }

    /**
     * Serve the proof of payment uploaded by a Superadmin for a company
     * transfer request through the same authorised document flow.
     */
    public function showPaymentProofAttachment(Tuntutan $tuntutan, ClaimDocumentService $claimDocuments)
    {
        return $this->showDocument(
            $tuntutan,
            Tuntutan::DOCUMENT_PAYMENT_PROOF_ATTACHMENT,
            $claimDocuments,
        );
    }

    /**
     * Stream an authorised claim document after validating its known storage
     * location. The service records Superadmin views only after existence is
     * established, so missing files never mutate claim audit data.
     */
    private function showDocument(
        Tuntutan $tuntutan,
        string $document,
        ClaimDocumentService $claimDocuments,
    ) {
        $user = Auth::user();

        if (! $claimDocuments->canAccess($tuntutan, $user)) {
            abort(403);
        }

        $resolvedDocument = $claimDocuments->openForUser($tuntutan, $document, $user);

        if ($resolvedDocument === null) {
            abort(404);
        }

        return Storage::disk($resolvedDocument['disk'])->response(
            $resolvedDocument['path'],
            $resolvedDocument['filename'],
            ['X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }

    /**
     * Simpan lampiran selepas permohonan Pantry/General diluluskan.
     */
    public function uploadAttachment(
        Request $request,
        Tuntutan $tuntutan,
        ClaimDocumentService $claimDocuments,
    )
    {
        $user = Auth::user();

        if (! $user->hasRole('Stocker') || $tuntutan->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $result = DB::transaction(function () use ($request, $tuntutan, $claimDocuments) {
            $claim = Tuntutan::query()->lockForUpdate()->findOrFail($tuntutan->id);

            if (! $claim->canUploadAttachment()) {
                return null;
            }

            $oldData = $claim->toArray();
            $claim->update([
                'attachment' => $claimDocuments->store($request->file('attachment')),
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

        return back()->with('success', 'Dokumen berjaya dimuat naik. Permohonan telah lengkap.');
    }

    /**
     * Store one company-transfer proof of payment after a request has been
     * approved. Only Superadmins can complete this final workflow step.
     */
    public function uploadPaymentProof(
        Request $request,
        Tuntutan $tuntutan,
        ClaimDocumentService $claimDocuments,
    ) {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin')) {
            abort(403);
        }

        $request->validate([
            'payment_proof_attachment' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
        ]);

        $result = DB::transaction(function () use ($request, $tuntutan, $claimDocuments) {
            $claim = Tuntutan::query()->lockForUpdate()->findOrFail($tuntutan->id);

            if (! $claim->canUploadPaymentProof()) {
                return null;
            }

            $oldData = $claim->toArray();
            $claim->update([
                'payment_proof_attachment' => $claimDocuments->store($request->file('payment_proof_attachment')),
                'status' => 'Completed',
            ]);

            return [$oldData, $claim];
        });

        if ($result === null) {
            return back()->with('error', 'Bukti pembayaran hanya boleh dimuat naik sekali selepas permohonan transfer syarikat diluluskan.');
        }

        [$oldData, $claim] = $result;

        LogAktiviti::create([
            'user_id' => $user->id,
            'aktiviti' => "{$user->name} telah memuat naik bukti pembayaran dan melengkapkan permohonan ID {$claim->id} ({$claim->nama_item}).",
            'item_id' => null,
            'data_lama' => $oldData,
            'data_baru' => $claim->toArray(),
        ]);

        return back()->with('success', 'Bukti pembayaran berjaya dimuat naik. Permohonan telah lengkap.');
    }

    /**
     * Simpan permohonan pembelian atau tuntutan Lunch mingguan.
     */
    public function store(Request $request, ClaimDocumentService $claimDocuments)
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
            $this->storeLunchClaims($request, $user->id, $user->name, $claimDocuments);
        } else {
            $this->storePurchaseRequest($request, $user->id, $user->name, $tag, $claimDocuments);
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
            $claim->update([
                'status' => $this->statusAfterReview($claim, $validated['approval_result']),
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

        $message = match (true) {
            $claim->canUploadAttachment() => 'Permohonan diluluskan. Pemohon boleh memuat naik dokumen yang diperlukan.',
            $claim->canUploadPaymentProof() => 'Permohonan diluluskan. Superadmin perlu memuat naik bukti pembayaran.',
            default => 'Keputusan permohonan berjaya direkodkan.',
        };

        return back()->with('success', $message);
    }

    /**
     * Record the latest Superadmin claim-detail review for the audit display.
     */
    public function recordDetailsViewed(Tuntutan $tuntutan, ClaimDocumentService $claimDocuments)
    {
        $user = Auth::user();

        if (! $user->hasRole('Superadmin')) {
            abort(403);
        }

        $claim = $claimDocuments->recordClaimDetailsViewed($tuntutan, $user);
        $viewedAt = $claim->claim_details_viewed_at;

        return response()->json([
            'claim_details_viewed_at' => $viewedAt?->toIso8601String(),
            'claim_details_viewed_at_display' => $viewedAt
                ?->timezone(config('app.timezone', 'Asia/Kuala_Lumpur'))
                ->format('d/m/Y, H:i'),
        ]);
    }

    private function storePurchaseRequest(
        Request $request,
        int $userId,
        string $requestorName,
        string $tag,
        ClaimDocumentService $claimDocuments,
    ): void
    {
        $isOtherPaymentMethod = $request->input('payment_method') === Tuntutan::OTHER_PAYMENT_METHOD;
        $paymentMethodRules = ['required', 'string', 'max:255'];
        $otherPaymentMethodRules = ['nullable', 'string', 'max:255'];

        if ($isOtherPaymentMethod) {
            $paymentMethodRules[] = Rule::in([Tuntutan::OTHER_PAYMENT_METHOD]);
            $otherPaymentMethodRules[] = Rule::in([Tuntutan::OTHER_PAYMENT_METHOD_DETAIL]);
        } else {
            $paymentMethodRules[] = Rule::exists('tuntutan_presets', 'name')
                ->where('type', TuntutanPreset::TYPE_PAYMENT_METHOD)
                ->whereIn('payment_workflow', TuntutanPreset::paymentWorkflows());
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
            'invoice_sent_to_account' => ['nullable', 'boolean'],
            'date_receive' => ['required', 'date', 'after_or_equal:request_date'],
            'attachment' => ['prohibited'],
            'payment_proof_attachment' => ['prohibited'],
            'payment_workflow' => ['prohibited'],
        ], [
            'purchase_platform.exists' => 'Sila pilih platform pembelian yang telah ditetapkan oleh Superadmin.',
            'payment_method.exists' => 'Sila pilih kaedah bayaran yang telah dikonfigurasi oleh Superadmin.',
            'other_payment_method.in' => 'Sila nyatakan kaedah pembayaran',
            'date_receive.after_or_equal' => 'Tarikh terima tidak boleh sebelum tarikh permohonan.',
        ]);

        $paymentWorkflow = $isOtherPaymentMethod
            ? Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES
            : TuntutanPreset::query()
                ->forType(TuntutanPreset::TYPE_PAYMENT_METHOD)
                ->where('name', $validated['payment_method'])
                ->whereIn('payment_workflow', TuntutanPreset::paymentWorkflows())
                ->value('payment_workflow');

        if (! is_string($paymentWorkflow) || $paymentWorkflow === '') {
            throw new HttpResponseException(
                back()->withInput()->withErrors(['payment_method' => 'Sila pilih kaedah bayaran yang telah dikonfigurasi oleh Superadmin.'])
            );
        }

        $isDirectorCreditCard = $paymentWorkflow === Tuntutan::PAYMENT_WORKFLOW_DIRECTOR_CC;
        $invoiceSentToAccount = $isDirectorCreditCard
            ? (bool) ($validated['invoice_sent_to_account'] ?? false)
            : false;
        $requiresPreApprovalDocument = $paymentWorkflow === Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER
            || ($isDirectorCreditCard && $invoiceSentToAccount);

        $request->validate([
            'invoice_sent_to_account' => $isDirectorCreditCard
                ? ['required', 'boolean']
                : ['prohibited'],
            'purchase_attachment' => $requiresPreApprovalDocument
                ? ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120']
                : ['prohibited'],
        ], [
            'purchase_attachment.required' => 'Sila muat naik dokumen yang diperlukan sebelum menghantar permohonan.',
        ]);

        $requestDate = Carbon::parse($validated['request_date']);
        $purchaseAttachmentPath = $request->hasFile('purchase_attachment')
            ? $claimDocuments->store($request->file('purchase_attachment'))
            : null;
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
            'other_payment_method' => $isOtherPaymentMethod ? Tuntutan::OTHER_PAYMENT_METHOD_DETAIL : null,
            'payment_workflow' => $paymentWorkflow,
            'invoice_sent_to_account' => $isDirectorCreditCard ? $invoiceSentToAccount : null,
            'request_date' => $validated['request_date'],
            'date_receive' => $validated['date_receive'],
            'tarikh_beli' => $validated['request_date'],
            'minggu_tuntutan' => $this->weekFor($requestDate),
            'status' => 'Pending',
            'purchase_attachment' => $purchaseAttachmentPath,
        ]);

        LogAktiviti::create([
            'user_id' => $userId,
            'aktiviti' => "Menghantar permohonan pembelian {$claim->tag}: {$claim->nama_item} bernilai RM{$claim->nilai_tuntutan}.",
            'item_id' => null,
            'data_baru' => $claim->toArray(),
        ]);
    }

    /**
     * Preserve the established status values while deciding whether an
     * approved request has a remaining workflow action.
     */
    private function statusAfterReview(Tuntutan $claim, string $approvalResult): string
    {
        if ($approvalResult !== 'Approved' || ! $claim->isPurchaseRequest()) {
            return 'Completed';
        }

        if ($claim->payment_workflow === Tuntutan::PAYMENT_WORKFLOW_DIRECTOR_CC) {
            return $claim->invoice_sent_to_account && $claim->purchase_attachment !== null
                ? 'Completed'
                : 'Pending';
        }

        if (
            $claim->payment_workflow === Tuntutan::PAYMENT_WORKFLOW_LEGACY
            || $claim->payment_workflow === null
        ) {
            return $claim->attachment !== null ? 'Completed' : 'Pending';
        }

        return 'Pending';
    }

    private function storeLunchClaims(
        Request $request,
        int $userId,
        string $requestorName,
        ClaimDocumentService $claimDocuments,
    ): void
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
            'purchase_attachment' => ['prohibited'],
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

        $attachmentPath = $this->storeAttachment($request, $claimDocuments);

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

    private function storeAttachment(Request $request, ClaimDocumentService $claimDocuments): ?string
    {
        return $request->hasFile('attachment')
            ? $claimDocuments->store($request->file('attachment'))
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

    /**
     * Return valid, unique ISO-week filters from the request query string.
     *
     * @return array<int, string>
     */
    private function selectedWeeks(Request $request): array
    {
        $weeks = $request->query('weeks', []);

        if (! is_array($weeks)) {
            return [];
        }

        return collect($weeks)
            ->filter(fn ($week) => is_string($week))
            ->map(fn (string $week) => trim($week))
            ->filter(fn (string $week) => $this->isValidIsoWeek($week))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * Return one supported claim type from the query string, if selected.
     */
    private function selectedType(Request $request): ?string
    {
        $type = $request->query('type');

        if (! is_string($type)) {
            return null;
        }

        $type = trim($type);

        return in_array($type, Tuntutan::FILTERABLE_TYPES, true) ? $type : null;
    }

    /**
     * Return one supported visible workflow status from the query string.
     */
    private function selectedStatus(Request $request): ?string
    {
        $status = $request->query('status');

        if (! is_string($status)) {
            return null;
        }

        $status = trim($status);

        return in_array($status, Tuntutan::FILTERABLE_WORKFLOW_STATUSES, true)
            ? $status
            : null;
    }

    private function isValidIsoWeek(string $week): bool
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches) !== 1) {
            return false;
        }

        $year = (int) $matches[1];
        $weekNumber = (int) $matches[2];

        if ($year < 1 || $weekNumber < 1 || $weekNumber > 53) {
            return false;
        }

        return Carbon::create($year, 1, 4)
            ->setISODate($year, $weekNumber)
            ->format('o-\\WW') === $week;
    }

    private function calendarMonth(Request $request): Carbon
    {
        $month = $request->query('month');

        if (! is_string($month) || preg_match('/^(\d{4})-(\d{2})$/', $month, $matches) !== 1) {
            return now()->startOfMonth();
        }

        $year = (int) $matches[1];
        $monthNumber = (int) $matches[2];

        if ($year < 1 || $monthNumber < 1 || $monthNumber > 12) {
            return now()->startOfMonth();
        }

        return Carbon::create($year, $monthNumber, 1)->startOfMonth();
    }

    /**
     * Build complete Monday-Sunday rows for the month displayed in the filter.
     *
     * @return array<int, array{value: string, number: int, start: Carbon, end: Carbon, days: array<int, Carbon>}>
     */
    private function calendarWeeks(Carbon $month): array
    {
        $calendarWeeks = [];
        $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $lastDay = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        while ($cursor->lte($lastDay)) {
            $weekStart = $cursor->copy();
            $days = [];

            for ($offset = 0; $offset < 7; $offset++) {
                $days[] = $weekStart->copy()->addDays($offset);
            }

            $calendarWeeks[] = [
                'value' => $weekStart->format('o-\\WW'),
                'number' => $weekStart->isoWeek,
                'start' => $weekStart,
                'end' => $weekStart->copy()->addDays(6),
                'days' => $days,
            ];

            $cursor->addWeek();
        }

        return $calendarWeeks;
    }
}
