@props(['claim', 'context' => 'dialog'])

@php
    $isPurchaseRequest = $claim->isPurchaseRequest();
    $attachmentInputId = "attachment-{$context}-{$claim->id}";
    $paymentProofInputId = "payment-proof-{$context}-{$claim->id}";
    $isSuperadmin = Auth::user()?->hasRole('Superadmin') ?? false;
    $isOwner = Auth::id() === $claim->user_id;
    $isDirectorCreditCardPayment = $isPurchaseRequest && $claim->isDirectorCreditCardPayment();
    $isOwnExpensesPayment = $isPurchaseRequest && $claim->isOwnExpensesPayment();
    $purchaseAttachmentLabel = $isDirectorCreditCardPayment ? 'Invoice' : 'Invoice/Quotation';
    $attachmentLabel = $isOwnExpensesPayment
        ? 'Receipt/Invoice'
        : ($isDirectorCreditCardPayment ? 'Invoice' : 'Receipt');
    $attachmentUploadLabel = $isOwnExpensesPayment
        ? 'Receipt or invoice required to complete this claim'
        : ($isDirectorCreditCardPayment
            ? 'Invoice required to complete this claim'
            : 'Receipt required to complete this claim');
    $attachmentUploadHelp = $isOwnExpensesPayment
        ? 'Upload the final receipt or invoice after approval to complete this request.'
        : ($isDirectorCreditCardPayment
            ? 'Upload the invoice after approval to complete this request.'
            : 'Upload the final receipt to complete this approved request.');
    $purchaseAttachmentAwaitingView = $isSuperadmin
        && filled($claim->purchase_attachment)
        && method_exists($claim, 'isDocumentAwaitingView')
        && $claim->isDocumentAwaitingView('purchase_attachment');
    $attachmentAwaitingView = $isSuperadmin
        && filled($claim->attachment)
        && method_exists($claim, 'isDocumentAwaitingView')
        && $claim->isDocumentAwaitingView('attachment');
    $paymentProofAwaitingView = $isSuperadmin
        && filled($claim->payment_proof_attachment)
        && method_exists($claim, 'isDocumentAwaitingView')
        && $claim->isDocumentAwaitingView('payment_proof_attachment');
    $showPurchaseAttachment = $isPurchaseRequest && filled($claim->purchase_attachment);
    $showAttachment = filled($claim->attachment);
    $showPaymentProof = $isPurchaseRequest && filled($claim->payment_proof_attachment) && ($isSuperadmin || $isOwner);
    $canUploadAttachment = $claim->canUploadAttachment() && $isOwner;
    $canUploadPaymentProof = $isSuperadmin && $claim->canUploadPaymentProof();
@endphp

@if($showPurchaseAttachment || $showAttachment || $showPaymentProof || $canUploadAttachment || $canUploadPaymentProof)
    <section class="claim-document-actions" aria-label="Claim documents">
        @if($showPurchaseAttachment || $showAttachment || $showPaymentProof)
            <div class="claim-document-links">
                @if($showPurchaseAttachment)
                    <div class="claim-attachment-link">
                        <a
                            href="{{ route('tuntutan.purchase-attachment', $claim) }}"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-secondary btn-sm claim-document-action {{ $purchaseAttachmentAwaitingView ? 'claim-document-awaiting-view' : '' }}"
                            data-attachment-open-link
                        >
                            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                            <span data-attachment-open-label>{{ $purchaseAttachmentLabel }}</span>
                        </a>
                        <span class="sr-only" data-attachment-open-status role="status" aria-live="polite"></span>
                    </div>
                @endif

                @if($showAttachment)
                    <div class="claim-attachment-link">
                        <a
                            href="{{ route('tuntutan.attachment', $claim) }}"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-secondary btn-sm claim-document-action {{ $attachmentAwaitingView ? 'claim-document-awaiting-view' : '' }}"
                            data-attachment-open-link
                        >
                            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                            <span data-attachment-open-label>{{ $isPurchaseRequest ? $attachmentLabel : 'Supporting document' }}</span>
                        </a>
                        <span class="sr-only" data-attachment-open-status role="status" aria-live="polite"></span>

                        @if($isPurchaseRequest && $claim->receipt_viewed_at)
                            <p class="claim-receipt-viewed">
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                {{ $attachmentLabel }} viewed by {{ $claim->receiptViewer?->name ?? 'a Superadmin' }} on {{ $claim->receipt_viewed_at->format('d/m/Y, H:i') }}
                            </p>
                        @endif
                    </div>
                @endif

                @if($showPaymentProof)
                    <div class="claim-attachment-link">
                        <a
                            href="{{ route('tuntutan.payment-proof', $claim) }}"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-secondary btn-sm claim-document-action {{ $paymentProofAwaitingView ? 'claim-document-awaiting-view' : '' }}"
                            data-attachment-open-link
                            @if($isOwner) data-payment-proof-review-link @endif
                        >
                            <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                            <span data-attachment-open-label>Proof of Payment</span>
                        </a>
                        <span class="sr-only" data-attachment-open-status role="status" aria-live="polite"></span>
                    </div>
                @endif
            </div>
        @endif

        @if($canUploadAttachment || $canUploadPaymentProof)
            <div class="claim-document-uploads">
                @if($canUploadAttachment)
                    <form
                        action="{{ route('tuntutan.attachment.store', $claim) }}"
                        method="POST"
                        enctype="multipart/form-data"
                        class="claim-attachment-upload"
                        data-receipt-upload-form
                        data-file-upload-form
                        data-file-required-message="Sila pilih dokumen yang sah sebelum memuat naik."
                        data-file-upload-area
                    >
                        @csrf
                        <label for="{{ $attachmentInputId }}" class="claim-attachment-upload-label">{{ $attachmentUploadLabel }}</label>
                        <p id="{{ $attachmentInputId }}-help" class="claim-attachment-upload-help">{{ $attachmentUploadHelp }}</p>
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
                            <strong data-file-prompt>Drag &amp; drop your document here</strong>
                            <span>or choose a file</span>
                            <small>JPG, JPEG, PNG atau PDF &middot; Maksimum 5 MB</small>
                        </div>
                        <div class="claim-file-selection" data-file-selection hidden>
                            <i class="fa-solid fa-file" aria-hidden="true"></i>
                            <span data-file-name></span>
                            <button type="button" class="claim-file-remove" data-file-remove aria-label="Remove selected document">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                <span>Remove</span>
                            </button>
                        </div>
                        <p id="{{ $attachmentInputId }}-status" class="claim-file-status" data-file-status role="status" aria-live="polite"></p>
                        <div class="claim-attachment-controls">
                            <button type="submit" class="btn btn-primary btn-sm claim-file-submit" data-file-submit disabled aria-disabled="true">
                                <i class="fa-solid fa-upload" aria-hidden="true"></i>
                                Upload document
                            </button>
                        </div>
                        @error('attachment')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </form>
                @endif

                @if($canUploadPaymentProof)
                    <form
                        action="{{ route('tuntutan.payment-proof.store', $claim) }}"
                        method="POST"
                        enctype="multipart/form-data"
                        class="claim-attachment-upload"
                        data-file-upload-form
                        data-file-required-message="Sila pilih bukti pembayaran yang sah sebelum memuat naik."
                        data-file-upload-area
                    >
                        @csrf
                        <label for="{{ $paymentProofInputId }}" class="claim-attachment-upload-label">Proof of payment required to complete this request</label>
                        <p id="{{ $paymentProofInputId }}-help" class="claim-attachment-upload-help">Upload the company payment proof after approval to complete this request.</p>
                        <div
                            class="claim-file-dropzone claim-file-dropzone-receipt"
                            data-file-dropzone
                            role="button"
                            tabindex="0"
                            aria-describedby="{{ $paymentProofInputId }}-help {{ $paymentProofInputId }}-status"
                        >
                            <input
                                type="file"
                                id="{{ $paymentProofInputId }}"
                                name="payment_proof_attachment"
                                class="claim-file-input"
                                data-file-input
                                accept=".jpg,.jpeg,.png,.pdf"
                                tabindex="-1"
                                aria-describedby="{{ $paymentProofInputId }}-help {{ $paymentProofInputId }}-status"
                                required
                            >
                            <i class="fa-solid fa-cloud-arrow-up claim-file-dropzone-icon" aria-hidden="true"></i>
                            <strong data-file-prompt>Drag &amp; drop company payment proof here</strong>
                            <span>or choose a file</span>
                            <small>JPG, JPEG, PNG atau PDF &middot; Maksimum 5 MB</small>
                        </div>
                        <div class="claim-file-selection" data-file-selection hidden>
                            <i class="fa-solid fa-file" aria-hidden="true"></i>
                            <span data-file-name></span>
                            <button type="button" class="claim-file-remove" data-file-remove aria-label="Remove selected payment proof">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                <span>Remove</span>
                            </button>
                        </div>
                        <p id="{{ $paymentProofInputId }}-status" class="claim-file-status" data-file-status role="status" aria-live="polite"></p>
                        <div class="claim-attachment-controls">
                            <button type="submit" class="btn btn-primary btn-sm claim-file-submit" data-file-submit disabled aria-disabled="true">
                                <i class="fa-solid fa-upload" aria-hidden="true"></i>
                                Upload proof of payment
                            </button>
                        </div>
                        @error('payment_proof_attachment')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </form>
                @endif
            </div>
        @endif
    </section>
@endif
