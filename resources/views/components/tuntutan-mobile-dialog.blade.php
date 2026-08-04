@props(['claim', 'modalId'])

@php
    $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
    $requestDate = $claim->request_date ?? $claim->tarikh_beli;
    $requesterName = $claim->requestor_name ?: $claim->user->name;
@endphp

<dialog id="{{ $modalId }}" class="claim-mobile-modal" aria-labelledby="{{ $modalId }}-title">
    <div class="card claim-mobile-modal-card">
        <header class="claim-mobile-modal-header">
            <div>
                <p class="claim-mobile-dialog-kicker" id="{{ $modalId }}-title">Claim details</p>
                <strong class="claim-mobile-requester">Submitted by {{ $requesterName }}</strong>
                <span class="claim-mobile-email">{{ $claim->user->email }}</span>
            </div>
            <button type="button" class="claim-mobile-modal-close" data-claim-modal-close aria-label="Close claim details">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="claim-mobile-modal-type">
            <x-tuntutan-type-badge :claim="$claim" />
        </div>

        <x-tuntutan-details :claim="$claim" context="mobile-modal" />

        <div class="claim-mobile-meta">
            <div>
                <span>Amount</span>
                <strong>RM {{ number_format($amount, 2) }}</strong>
            </div>
            <div>
                <span>{{ $claim->isPurchaseRequest() ? 'Requested' : 'Claim date' }}</span>
                <strong>{{ $requestDate?->format('d/m/Y') ?? '-' }}</strong>
            </div>
            @if($claim->isPurchaseRequest())
                <div>
                    <span>Received</span>
                    <strong>{{ $claim->date_receive?->format('d/m/Y') ?? '-' }}</strong>
                </div>
            @endif
        </div>

        <x-tuntutan-status :claim="$claim" />
        <x-tuntutan-actions :claim="$claim" />
    </div>
</dialog>
