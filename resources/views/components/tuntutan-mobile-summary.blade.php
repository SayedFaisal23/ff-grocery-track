@props(['claim', 'modalId'])

@php
    $itemName = $claim->isPurchaseRequest()
        ? ($claim->item_specification ?: $claim->nama_item)
        : $claim->nama_item;
    $requesterName = $claim->requestor_name ?: $claim->user->name;
@endphp

<button
    type="button"
    class="claim-mobile-summary"
    data-claim-modal-open="{{ $modalId }}"
    aria-haspopup="dialog"
    aria-controls="{{ $modalId }}"
    aria-label="View details for {{ $itemName }}"
>
    <span class="claim-mobile-summary-main">
        <strong class="claim-mobile-item-name">{{ $itemName }}</strong>
        <x-tuntutan-type-badge :claim="$claim" />
    </span>
    <span class="claim-mobile-summary-submitter">
        <span>Submitted by</span>
        <strong>{{ $requesterName }}</strong>
    </span>
    <x-tuntutan-status :claim="$claim" :compact="true" />
    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
</button>
