@props(['claim'])

@if($claim->isPurchaseRequest())
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
        @if($claim->isDirectorCreditCardPayment())
            <div class="claim-detail-row">
                <dt>INVOICE SENT TO ACCOUNT:</dt>
                <dd>{{ $claim->invoice_sent_to_account ? 'Yes' : 'No' }}</dd>
            </div>
        @endif
    </dl>
@endif
