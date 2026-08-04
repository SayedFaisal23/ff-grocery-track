@props(['claim', 'context' => 'default'])

@php
    $isPurchaseRequest = $claim->isPurchaseRequest();
    $itemName = $isPurchaseRequest ? ($claim->item_specification ?: $claim->nama_item) : $claim->nama_item;
    $attachmentInputId = "attachment-{$context}-{$claim->id}";
@endphp

<div class="claim-details">
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

    @if($claim->attachment)
        <div class="claim-attachment-link">
            <a href="{{ route('tuntutan.attachment', $claim) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" data-attachment-open-link>
                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                <span data-attachment-open-label>Supporting document</span>
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
        <form action="{{ route('tuntutan.attachment.store', $claim) }}" method="POST" enctype="multipart/form-data" class="claim-attachment-upload">
            @csrf
            <label for="{{ $attachmentInputId }}">Receipt required to complete this claim</label>
            <div class="claim-attachment-controls">
                <input type="file" id="{{ $attachmentInputId }}" name="attachment" accept=".jpg,.jpeg,.png,.pdf" required>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-upload"></i> Upload receipt</button>
            </div>
            @error('attachment')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </form>
    @endif
</div>
