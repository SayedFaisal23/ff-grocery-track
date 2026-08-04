@props(['claim', 'context' => 'default'])

@php
    $isPurchaseRequest = $claim->isPurchaseRequest();
    $itemName = $isPurchaseRequest ? ($claim->item_specification ?: $claim->nama_item) : $claim->nama_item;
    $attachmentInputId = "attachment-{$context}-{$claim->id}";
@endphp

<div class="claim-details">
    <h3 class="claim-item-name">{{ $itemName }}</h3>

    @if($isPurchaseRequest)
        <dl class="claim-detail-grid">
            <div class="claim-detail-item claim-detail-purpose">
                <dt>Purpose</dt>
                <dd>{{ $claim->purchase_purpose }}</dd>
            </div>
            @if($claim->invoice_no)
                <div class="claim-detail-item">
                    <dt>Invoice no.</dt>
                    <dd>{{ $claim->invoice_no }}</dd>
                </div>
            @endif
            <div class="claim-detail-item">
                <dt>Purchase platform</dt>
                <dd>{{ $claim->purchase_platform }}</dd>
            </div>
            <div class="claim-detail-item">
                <dt>Payment method</dt>
                <dd>{{ $claim->paymentMethodDisplay() }}</dd>
            </div>
            <div class="claim-detail-item">
                <dt>Invoice sent to account</dt>
                <dd>{{ $claim->invoice_sent_to_account ? 'Yes' : 'No' }}</dd>
            </div>
        </dl>
    @endif

    @if($claim->attachment)
        <div class="claim-attachment-link">
            <a href="{{ route('tuntutan.attachment', $claim) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-paperclip"></i> Supporting document
            </a>
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
