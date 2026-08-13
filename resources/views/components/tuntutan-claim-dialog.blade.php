@props(['claim', 'modalId'])

@php
    $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
    $requestDate = $claim->request_date ?? $claim->tarikh_beli;
    $requesterName = $claim->requestor_name ?: $claim->user->name;
    $itemName = $claim->isPurchaseRequest()
        ? ($claim->item_specification ?: $claim->nama_item)
        : $claim->nama_item;
    $isSuperadmin = Auth::user()?->hasRole('Superadmin') ?? false;
@endphp

<dialog
    id="{{ $modalId }}"
    class="claim-details-modal claim-dialog claim-mobile-modal"
    data-claim-modal
    aria-labelledby="{{ $modalId }}-title"
>
    <div class="card claim-details-dialog-card claim-mobile-modal-card">
        <header class="claim-details-header claim-mobile-modal-header">
            <div>
                <p class="claim-dialog-kicker claim-mobile-dialog-kicker">Claim details</p>
                <h2 id="{{ $modalId }}-title" class="claim-dialog-title">{{ $itemName }}</h2>
            </div>
            <button
                type="button"
                class="claim-dialog-close claim-mobile-modal-close"
                data-claim-modal-close
                aria-label="Close claim details"
            >
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="claim-dialog-status-row">
            <div class="claim-dialog-status">
                <span class="claim-dialog-status-label">Status:</span>
                <x-tuntutan-status :claim="$claim" :compact="true" />
            </div>
            <div class="claim-dialog-type claim-mobile-modal-type">
                <x-tuntutan-type-badge :claim="$claim" />
            </div>
        </div>

        <div
            class="claim-details"
            @if($isSuperadmin)
                data-claim-details-review
                data-claim-id="{{ $claim->id }}"
                data-claim-review-url="{{ route('tuntutan.details-viewed', $claim) }}"
            @endif
        >
            <x-tuntutan-details :claim="$claim" />

            <section class="claim-details-meta claim-mobile-meta" aria-label="Claim amount and dates">
                <div class="claim-meta-amount">
                    <span>Amount</span>
                    <strong>RM {{ number_format($amount, 2) }}</strong>
                </div>
                <div class="claim-meta-date {{ $claim->isPurchaseRequest() ? 'claim-meta-date-requested' : '' }}">
                    <span>{{ $claim->isPurchaseRequest() ? 'Requested' : 'Claim date' }}</span>
                    <strong>{{ $requestDate?->format('d/m/Y') ?? '-' }}</strong>
                </div>
                @if($claim->isPurchaseRequest())
                    <div class="claim-meta-date claim-meta-date-received">
                        <span>Received</span>
                        <strong>{{ $claim->date_receive?->format('d/m/Y') ?? '-' }}</strong>
                    </div>
                @endif
            </section>

            <x-tuntutan-document-actions :claim="$claim" context="dialog" />
            <x-tuntutan-review-audit :claim="$claim" />
        </div>

        <footer class="claim-details-footer">
            <div class="claim-submitter">
                <h3>Submitted by: {{ $requesterName }}</h3>
                <p>{{ $claim->user->email }}</p>
            </div>
            <div class="claim-details-workflow">
                <x-tuntutan-status :claim="$claim" :show-badge="false" />
                @role('Superadmin')
                    <x-tuntutan-actions :claim="$claim" />
                @endrole
            </div>
        </footer>
    </div>
</dialog>
