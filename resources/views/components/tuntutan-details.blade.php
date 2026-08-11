@props(['claim', 'context' => 'default'])

@php
    $isPurchaseRequest = $claim->isPurchaseRequest();
    $itemName = $isPurchaseRequest ? ($claim->item_specification ?: $claim->nama_item) : $claim->nama_item;
    $attachmentInputId = "attachment-{$context}-{$claim->id}";
    $isSuperadmin = Auth::user()?->hasRole('Superadmin') ?? false;
    $purchaseAttachmentAwaitingView = $isSuperadmin
        && filled($claim->purchase_attachment)
        && method_exists($claim, 'isDocumentAwaitingView')
        && $claim->isDocumentAwaitingView('purchase_attachment');
    $attachmentAwaitingView = $isSuperadmin
        && filled($claim->attachment)
        && method_exists($claim, 'isDocumentAwaitingView')
        && $claim->isDocumentAwaitingView('attachment');
    $formatAuditDate = static fn ($timestamp, string $fallback = 'Not opened yet'): string => $timestamp?->copy()
        ->timezone('Asia/Kuala_Lumpur')
        ->format('d/m/Y, H:i') ?? $fallback;
@endphp

<div
    class="claim-details"
    @if($isSuperadmin)
        data-claim-details-review
        data-claim-details-context="{{ $context }}"
        data-claim-id="{{ $claim->id }}"
        data-claim-review-url="{{ route('tuntutan.details-viewed', $claim) }}"
    @endif
>
    <h3 class="claim-item-name">{{ $itemName }}</h3>

    @if($isPurchaseRequest)
        <dl class="claim-detail-rows">
            <div class="claim-detail-row">
                <dt>PURPOSE:</dt>
                <dd>{{ $claim->purchase_purpose }}</dd>
            </div>
            <div class="claim-detail-row">
                <dt>INVOICE NO.:</dt>
                <dd>{{ filled($claim->invoice_no) ? $claim->invoice_no : 'N/A' }}</dd>
            </div>
            <div class="claim-detail-row">
                <dt>PURCHASE PLATFORM:</dt>
                <dd>{{ $claim->purchase_platform }}</dd>
            </div>
            <div class="claim-detail-row">
                <dt>PAYMENT METHOD:</dt>
                <dd>{{ $claim->paymentMethodDisplay() }}</dd>
            </div>
            <div class="claim-detail-row">
                <dt>INVOICE SENT TO ACCOUNT:</dt>
                <dd>{{ $claim->invoice_sent_to_account ? 'Yes' : 'No' }}</dd>
            </div>
        </dl>
    @endif

    @if($isPurchaseRequest && $claim->purchase_attachment)
        <div class="claim-attachment-link">
            <a
                href="{{ route('tuntutan.purchase-attachment', $claim) }}"
                target="_blank"
                rel="noopener"
                class="btn btn-secondary btn-sm {{ $purchaseAttachmentAwaitingView ? 'claim-document-awaiting-view' : '' }}"
                data-attachment-open-link
            >
                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                <span data-attachment-open-label>Supporting document</span>
            </a>
            <span class="sr-only" data-attachment-open-status role="status" aria-live="polite"></span>
        </div>
    @endif

    @if($claim->attachment)
        <div class="claim-attachment-link">
            <a
                href="{{ route('tuntutan.attachment', $claim) }}"
                target="_blank"
                rel="noopener"
                class="btn btn-secondary btn-sm {{ $attachmentAwaitingView ? 'claim-document-awaiting-view' : '' }}"
                data-attachment-open-link
            >
                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                <span data-attachment-open-label>{{ $isPurchaseRequest ? 'Receipt' : 'Supporting document' }}</span>
            </a>
            <span class="sr-only" data-attachment-open-status role="status" aria-live="polite"></span>

            @if($claim->isPurchaseRequest() && $claim->receipt_viewed_at)
                <p class="claim-receipt-viewed">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Receipt viewed by {{ $claim->receiptViewer?->name ?? 'a Superadmin' }} on {{ $claim->receipt_viewed_at->format('d/m/Y, H:i') }}
                </p>
            @endif
        </div>
    @endif

    @if($claim->canUploadAttachment() && Auth::id() === $claim->user_id)
        <form action="{{ route('tuntutan.attachment.store', $claim) }}" method="POST" enctype="multipart/form-data" class="claim-attachment-upload" data-receipt-upload-form data-file-upload-area>
            @csrf
            <label for="{{ $attachmentInputId }}" class="claim-attachment-upload-label">Receipt required to complete this claim</label>
            <p id="{{ $attachmentInputId }}-help" class="claim-attachment-upload-help">Upload the final receipt to complete this approved request.</p>
            <div
                class="claim-file-dropzone claim-file-dropzone-receipt"
                data-file-dropzone
                role="button"
                tabindex="0"
                aria-describedby="{{ $attachmentInputId }}-help {{ $attachmentInputId }}-status"
            >
                <input
                    type="file"
                    id="{{ $attachmentInputId }}"
                    name="attachment"
                    class="claim-file-input"
                    data-file-input
                    accept=".jpg,.jpeg,.png,.pdf"
                    tabindex="-1"
                    aria-describedby="{{ $attachmentInputId }}-help {{ $attachmentInputId }}-status"
                    required
                >
                <i class="fa-solid fa-cloud-arrow-up claim-file-dropzone-icon" aria-hidden="true"></i>
                <strong data-file-prompt>Drag &amp; drop your receipt here</strong>
                <span>or choose a file</span>
                <small>JPG, JPEG, PNG atau PDF &middot; Maksimum 5 MB</small>
            </div>
            <div class="claim-file-selection" data-file-selection hidden>
                <i class="fa-solid fa-file" aria-hidden="true"></i>
                <span data-file-name></span>
                <button type="button" class="claim-file-remove" data-file-remove aria-label="Remove selected receipt">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    <span>Remove</span>
                </button>
            </div>
            <p id="{{ $attachmentInputId }}-status" class="claim-file-status" data-file-status role="status" aria-live="polite"></p>
            <div class="claim-attachment-controls">
                <button type="submit" class="btn btn-primary btn-sm claim-file-submit" data-file-submit disabled aria-disabled="true">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i>
                    Upload receipt
                </button>
            </div>
            @error('attachment')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </form>
    @endif

    @if($isSuperadmin)
        <dl class="claim-review-audit" data-claim-audit-for="{{ $claim->id }}">
            <div>
                <dt>Latest attachment download date and time</dt>
                <dd data-latest-attachment-downloaded-at>{{ $formatAuditDate($claim->latest_attachment_downloaded_at) }}</dd>
            </div>
            <div>
                <dt>Latest claim details review date and time</dt>
                <dd data-claim-details-viewed-at>{{ $formatAuditDate($claim->claim_details_viewed_at, 'Not reviewed yet') }}</dd>
            </div>
        </dl>
    @endif
</div>
