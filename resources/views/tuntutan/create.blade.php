@extends('layouts.app')

@section('title', 'Hantar Permohonan')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Borang Permohonan Pembelian</h1>
        <p>Hantar permohonan sebelum membuat pembelian</p>
    </div>
    <a href="{{ route('tuntutan.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="card" style="max-width: 800px;">
    <form action="{{ route('tuntutan.store') }}" method="POST" id="tuntutanForm" enctype="multipart/form-data">
        @csrf

        <div style="margin-bottom: 1.75rem;">
            <label class="form-label" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.6rem; display: block;">
                <i class="fa-solid fa-tag" style="color: var(--color-primary); margin-right: 6px;"></i>
                Jenis Tuntutan
            </label>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <label class="tag-pill">
                    <input type="radio" name="tag" value="Pantry" {{ old('tag') === 'Pantry' ? 'checked' : '' }} required>
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Pantry</span>
                </label>
                <label class="tag-pill">
                    <input type="radio" name="tag" value="General" {{ old('tag') === 'General' ? 'checked' : '' }}>
                    <i class="fa-solid fa-folder-open"></i>
                    <span>General</span>
                </label>
                <label class="tag-pill">
                    <input type="radio" name="tag" value="Lunch" {{ old('tag') === 'Lunch' ? 'checked' : '' }}>
                    <i class="fa-solid fa-utensils"></i>
                    <span>Lunch</span>
                </label>
            </div>
            @error('tag')
                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 6px;">{{ $message }}</div>
            @enderror
        </div>

        <section id="section-request" class="mode-section" style="display: none;">
            <div style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.1rem; margin-bottom: 0.25rem;">Purchase Request Form</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Lengkapkan semua maklumat pembelian sebelum membuat pesanan.</p>
            </div>

            @if($platforms->isEmpty() || $paymentMethods->isEmpty())
                <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    Superadmin perlu menetapkan pilihan platform pembelian dan kaedah bayaran terlebih dahulu.
                </div>
            @endif

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="requestor_name">Nama Pemohon</label>
                    <input type="text" id="requestor_name" class="form-control form-control-readonly" value="{{ Auth::user()->name }}" readonly aria-readonly="true">
                    <small style="color: var(--text-dark); display: block; margin-top: 4px;">Direkodkan daripada akaun anda.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="request_date">Tarikh</label>
                    <input type="date" id="request_date" name="request_date" class="form-control @error('request_date') is-invalid @enderror" value="{{ old('request_date', now()->toDateString()) }}" required>
                    @error('request_date')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="item_specification">Spesifikasi Item</label>
                <textarea id="item_specification" name="item_specification" class="form-control @error('item_specification') is-invalid @enderror" rows="3" maxlength="255" placeholder="Contoh: 10 kotak susu segar 1L" required>{{ old('item_specification') }}</textarea>
                @error('item_specification')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="purchase_purpose">Tujuan Pembelian</label>
                <textarea id="purchase_purpose" name="purchase_purpose" class="form-control @error('purchase_purpose') is-invalid @enderror" rows="3" maxlength="1000" placeholder="Nyatakan kegunaan pembelian ini" required>{{ old('purchase_purpose') }}</textarea>
                @error('purchase_purpose')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="invoice_no">No. Invois <span style="font-weight: 400; color: var(--text-dark);">(jika berkenaan)</span></label>
                    <input type="text" id="invoice_no" name="invoice_no" class="form-control @error('invoice_no') is-invalid @enderror" value="{{ old('invoice_no') }}" maxlength="255" placeholder="Contoh: INV-2026-001">
                    @error('invoice_no')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="purchase_platform">Platform Pembelian</label>
                    <select id="purchase_platform" name="purchase_platform" class="form-control @error('purchase_platform') is-invalid @enderror" required>
                        <option value="">Pilih platform</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->name }}" @selected(old('purchase_platform') === $platform->name)>{{ $platform->name }}</option>
                        @endforeach
                    </select>
                    @error('purchase_platform')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="total_item_amount">Jumlah Amaun Item (RM)</label>
                    <input type="number" id="total_item_amount" name="total_item_amount" class="form-control @error('total_item_amount') is-invalid @enderror" value="{{ old('total_item_amount') }}" min="0.01" step="0.01" placeholder="0.00" required>
                    @error('total_item_amount')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="payment_method">Saluran / Kaedah Bayaran</label>
                    <select id="payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                        <option value="">Pilih kaedah bayaran</option>
                        @foreach($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->name }}" @selected(old('payment_method') === $paymentMethod->name)>{{ $paymentMethod->name }}</option>
                        @endforeach
                        <option value="{{ \App\Models\Tuntutan::OTHER_PAYMENT_METHOD }}" @selected(old('payment_method') === \App\Models\Tuntutan::OTHER_PAYMENT_METHOD)>{{ \App\Models\Tuntutan::OTHER_PAYMENT_METHOD }}</option>
                    </select>
                    @error('payment_method')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div id="other-payment-method-group" class="form-group" hidden>
                <label class="form-label" for="other_payment_method">Sila nyatakan kaedah pembayaran</label>
                <input type="text" id="other_payment_method" name="other_payment_method" class="form-control form-control-readonly @error('other_payment_method') is-invalid @enderror" value="{{ \App\Models\Tuntutan::OTHER_PAYMENT_METHOD_DETAIL }}" readonly aria-readonly="true">
                @error('other_payment_method')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="invoice_sent_to_account">Invois Dihantar ke Akaun?</label>
                    <select id="invoice_sent_to_account" name="invoice_sent_to_account" class="form-control @error('invoice_sent_to_account') is-invalid @enderror" required>
                        <option value="" @selected(old('invoice_sent_to_account') === null)>Pilih jawapan</option>
                        <option value="1" @selected((string) old('invoice_sent_to_account') === '1')>Ya</option>
                        <option value="0" @selected((string) old('invoice_sent_to_account') === '0')>Tidak</option>
                    </select>
                    @error('invoice_sent_to_account')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="date_receive">Tarikh Terima</label>
                    <input type="date" id="date_receive" name="date_receive" class="form-control @error('date_receive') is-invalid @enderror" value="{{ old('date_receive', now()->toDateString()) }}" required>
                    @error('date_receive')
                        <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="request-supporting-document" data-file-upload-area>
                <label for="purchase_attachment" class="form-label request-supporting-document-label">
                    <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                    Invoice/Quotation
                    <span>(Optional)</span>
                </label>
                <p id="purchase-attachment-help" class="request-supporting-document-help">
                    Muat naik quotation atau invois untuk membantu semakan permohonan ini.
                </p>
                <div
                    class="claim-file-dropzone"
                    data-file-dropzone
                    role="button"
                    tabindex="0"
                    aria-describedby="purchase-attachment-help purchase-attachment-status"
                >
                    <input
                        type="file"
                        name="purchase_attachment"
                        id="purchase_attachment"
                        class="claim-file-input"
                        data-file-input
                        accept=".jpg,.jpeg,.png,.pdf"
                        tabindex="-1"
                        aria-describedby="purchase-attachment-help purchase-attachment-status"
                    >
                    <i class="fa-solid fa-cloud-arrow-up claim-file-dropzone-icon" aria-hidden="true"></i>
                    <strong data-file-prompt>Drag &amp; drop your file here</strong>
                    <span>or choose a file</span>
                    <small>JPG, JPEG, PNG atau PDF &middot; Maksimum 5 MB</small>
                </div>
                <div class="claim-file-selection" data-file-selection hidden>
                    <i class="fa-solid fa-file" aria-hidden="true"></i>
                    <span data-file-name></span>
                    <button type="button" class="claim-file-remove" data-file-remove aria-label="Buang fail dipilih">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        <span>Buang</span>
                    </button>
                </div>
                <p id="purchase-attachment-status" class="claim-file-status" data-file-status role="status" aria-live="polite"></p>
                @error('purchase_attachment')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </section>

        <section id="section-lunch" class="mode-section" style="display: none;">
            <div class="form-row" style="margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.6rem; display: block;" for="lunch_week">
                        <i class="fa-solid fa-calendar-week" style="color: var(--color-primary); margin-right: 6px;"></i>
                        Pilih Minggu
                    </label>
                    <input type="week" id="lunch_week" name="week" class="form-control" value="{{ old('week', \Carbon\Carbon::now()->format('o-\\WW')) }}">
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label class="form-label" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 0.75rem; display: block;">
                    <i class="fa-solid fa-calendar-day" style="color: var(--color-primary); margin-right: 6px;"></i>
                    Butiran Lunch Mengikut Hari
                </label>
                <div style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                    <div style="display: grid; grid-template-columns: 140px 1fr 90px 110px; background: var(--bg-surface-hover); border-bottom: 1px solid var(--border-color); padding: 0.6rem 0.75rem; gap: 12px;">
                        <span class="lunch-table-heading">Tarikh</span>
                        <span class="lunch-table-heading">Butiran Lunch</span>
                        <span class="lunch-table-heading" style="text-align: right;">Pax</span>
                        <span class="lunch-table-heading" style="text-align: right;">Harga/Pax</span>
                    </div>
                    <div id="lunchDaysRows"></div>
                </div>
                <div id="lunchInputError" style="color: var(--color-danger); font-size: 0.8rem; margin-top: 6px; display: none;"></div>
            </div>

            <div class="form-row" style="align-items: flex-end; margin-bottom: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label" style="display: flex; align-items: center; gap: 6px; font-weight: 600;" for="lunch_total_display">
                        Jumlah Tuntutan Minggu Ini (RM)
                        <span style="font-size: 0.75rem; color: var(--text-dark); font-weight: 400;">dikira secara automatik</span>
                    </label>
                    <input type="text" id="lunch_total_display" class="form-control" readonly value="0.00" style="background: var(--bg-surface-hover); color: var(--color-success); font-weight: 700;">
                </div>
            </div>
            <div id="lunchBottomError" style="color: var(--color-danger); font-size: 0.8rem; margin-top: -0.5rem; margin-bottom: 0.75rem; display: none;"></div>
        </section>

        <div id="section-prompt" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-dark);">
            <i class="fa-solid fa-hand-pointer" style="font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.4;"></i>
            <span style="font-size: 0.9rem;">Pilih jenis tuntutan di atas untuk mula mengisi borang.</span>
        </div>

        <div id="lunch-attachment-section" style="margin-top: 1.5rem; margin-bottom: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <label for="attachment" class="form-label" style="font-weight: 600; font-size: 0.95rem;">
                <i class="fa-solid fa-paperclip" style="color: var(--color-primary); margin-right: 6px;"></i>
                Muat Naik Dokumen Sokongan Lunch (Pilihan)
            </label>
            <input type="file" name="attachment" id="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".jpg,.jpeg,.png,.pdf" style="background: var(--bg-surface-hover); color: var(--text-main); border: 1px dashed var(--border-color); padding: 0.6rem;">
            <small style="color: var(--text-dark); display: block; margin-top: 4px;">Format yang dibenarkan: JPG, PNG, PDF (Maksimum 5MB)</small>
            @error('attachment')
                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('tuntutan.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Hantar Permohonan
            </button>
        </div>
    </form>
</div>

<style>
    .tag-pill { display: flex; align-items: center; gap: 8px; padding: 0.5rem 1.1rem; border-radius: 999px; border: 1.5px solid var(--border-color); background: var(--bg-surface-hover); color: var(--text-muted); font-size: 0.88rem; font-weight: 500; cursor: pointer; transition: border-color var(--transition-fast), background var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast); user-select: none; }
    .tag-pill input[type="radio"] { display: none; }
    .tag-pill:has(input:checked) { border-color: var(--color-primary); background: rgba(99, 102, 241, 0.12); color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
    .tag-pill:hover { border-color: rgba(99, 102, 241, 0.5); color: var(--text-main); }
    .lunch-table-heading { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .lunch-input { width: 100%; background: var(--bg-surface-hover); border: 1px solid transparent; border-radius: var(--radius-sm); padding: 0.45rem 0.65rem; font-size: 0.9rem; color: var(--text-main); }
    .lunch-input:focus { border-color: var(--color-primary); background: rgba(99, 102, 241, 0.08); outline: none; }
</style>

<script>
    const form = document.getElementById('tuntutanForm');
    const radios = document.querySelectorAll('input[name="tag"]');
    const requestSection = document.getElementById('section-request');
    const lunchSection = document.getElementById('section-lunch');
    const promptSection = document.getElementById('section-prompt');
    const lunchAttachmentSection = document.getElementById('lunch-attachment-section');
    const attachmentInput = document.getElementById('attachment');
    const paymentMethodSelect = document.getElementById('payment_method');
    const otherPaymentMethodGroup = document.getElementById('other-payment-method-group');
    const otherPaymentMethodInput = document.getElementById('other_payment_method');

    function updateOtherPaymentMethodField() {
        const isOtherPaymentMethod = paymentMethodSelect.value === @json(\App\Models\Tuntutan::OTHER_PAYMENT_METHOD);
        const isRequestEnabled = !paymentMethodSelect.disabled;

        otherPaymentMethodGroup.hidden = !isOtherPaymentMethod;
        otherPaymentMethodInput.disabled = !isOtherPaymentMethod || !isRequestEnabled;
        otherPaymentMethodInput.value = isOtherPaymentMethod ? @json(\App\Models\Tuntutan::OTHER_PAYMENT_METHOD_DETAIL) : '';
        otherPaymentMethodInput.required = false;
    }

    function setSectionEnabled(section, enabled) {
        section.querySelectorAll('input, select, textarea').forEach((input) => {
            input.disabled = !enabled;
        });
    }

    function onTagChange() {
        const tag = document.querySelector('input[name="tag"]:checked')?.value;
        const isRequest = tag === 'Pantry' || tag === 'General';
        const isLunch = tag === 'Lunch';

        requestSection.style.display = isRequest ? 'block' : 'none';
        lunchSection.style.display = isLunch ? 'block' : 'none';
        lunchAttachmentSection.style.display = isLunch ? 'block' : 'none';
        promptSection.style.display = tag ? 'none' : 'block';
        setSectionEnabled(requestSection, isRequest);
        setSectionEnabled(lunchSection, isLunch);
        attachmentInput.disabled = !isLunch;
        updateOtherPaymentMethodField();
    }

    radios.forEach((radio) => radio.addEventListener('change', onTagChange));
    paymentMethodSelect.addEventListener('change', updateOtherPaymentMethodField);

    const oldLunchData = {
        dates: @json(old('lunch_dates', [])),
        butirans: @json(old('lunch_butirans', [])),
        pax: @json(old('lunch_pax', [])),
        hargas: @json(old('lunch_hargas', [])),
    };
    const weekInput = document.getElementById('lunch_week');
    const totalDisplay = document.getElementById('lunch_total_display');

    function getDatesFromISOWeek(week) {
        const [yearPart, weekPart] = week.split('-W');
        const year = Number.parseInt(yearPart, 10);
        const weekNumber = Number.parseInt(weekPart, 10);
        if (!year || !weekNumber) return [];

        const fourthJanuary = new Date(year, 0, 4);
        const weekday = fourthJanuary.getDay() || 7;
        const monday = new Date(fourthJanuary);
        monday.setDate(fourthJanuary.getDate() - weekday + 1 + ((weekNumber - 1) * 7));

        return Array.from({ length: 7 }, (_, index) => {
            const date = new Date(monday);
            date.setDate(monday.getDate() + index);
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function calculateLunchTotal() {
        let total = 0;
        document.querySelectorAll('.lunch-pax-input').forEach((paxInput) => {
            const row = paxInput.closest('[data-lunch-row]');
            const pax = Number.parseInt(paxInput.value, 10) || 0;
            const price = Number.parseFloat(row.querySelector('.lunch-price-input').value) || 0;
            total += pax * price;
        });
        totalDisplay.value = total.toFixed(2);
    }

    function renderLunchRows() {
        const container = document.getElementById('lunchDaysRows');
        const dates = getDatesFromISOWeek(weekInput.value);
        const dayNames = ['Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu', 'Ahad'];
        container.innerHTML = '';

        if (!dates.length) {
            container.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted);">Sila pilih minggu di atas.</div>';
            return;
        }

        dates.forEach((date, index) => {
            const useOldData = oldLunchData.dates[index] === date;
            const butiran = useOldData ? (oldLunchData.butirans[index] || 'Lunch Claim') : 'Lunch Claim';
            const pax = useOldData && oldLunchData.pax[index] !== null ? oldLunchData.pax[index] : '';
            const price = useOldData && oldLunchData.hargas[index] !== null ? oldLunchData.hargas[index] : '5.00';
            const row = document.createElement('div');
            row.dataset.lunchRow = 'true';
            row.style.cssText = `display:grid;grid-template-columns:140px 1fr 90px 110px;gap:12px;padding:.4rem .75rem;align-items:center;${index < 6 ? 'border-bottom:1px solid var(--border-color);' : ''}`;
            row.innerHTML = `
                <div><strong style="font-size:.85rem;display:block;">${dayNames[index]}</strong><span style="font-size:.75rem;color:var(--text-muted);">${date.split('-').reverse().join('/')}</span><input type="hidden" name="lunch_dates[]" value="${date}"></div>
                <div><input type="text" name="lunch_butirans[]" class="lunch-input" value="${escapeHtml(butiran)}" placeholder="Butiran lunch..."></div>
                <div><input type="number" name="lunch_pax[]" class="lunch-input lunch-pax-input" value="${pax}" min="0" placeholder="0" style="text-align:right;"></div>
                <div><input type="number" name="lunch_hargas[]" class="lunch-input lunch-price-input" value="${price}" min="0" step="0.01" placeholder="0.00" style="text-align:right;"></div>
            `;
            container.appendChild(row);
        });

        setSectionEnabled(lunchSection, document.querySelector('input[name="tag"]:checked')?.value === 'Lunch');
        container.querySelectorAll('.lunch-pax-input, .lunch-price-input').forEach((input) => input.addEventListener('input', calculateLunchTotal));
        calculateLunchTotal();
    }

    weekInput.addEventListener('change', renderLunchRows);
    renderLunchRows();
    onTagChange();

    form.addEventListener('submit', (event) => {
        const tag = document.querySelector('input[name="tag"]:checked')?.value;
        if (tag !== 'Lunch') return;

        const rows = document.querySelectorAll('[data-lunch-row]');
        const hasClaim = Array.from(rows).some((row) => (Number.parseInt(row.querySelector('.lunch-pax-input').value, 10) || 0) > 0);
        if (!weekInput.value || !hasClaim) {
            event.preventDefault();
            document.getElementById('lunchBottomError').textContent = !weekInput.value ? 'Sila pilih minggu.' : 'Sila tuntut sekurang-kurangnya untuk satu hari.';
            document.getElementById('lunchBottomError').style.display = 'block';
        }
    });
</script>
@endsection
