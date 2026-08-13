@props(['claim', 'compact' => false, 'showBadge' => true])

@php($workflow = $claim->workflowStatus())

@if($compact)
    <span class="claim-status claim-status-compact" data-claim-status="{{ $workflow['label'] }}">
        @if($showBadge)
            <span class="badge badge-{{ $workflow['tone'] }} claim-status-badge">{{ $workflow['label'] }}</span>
        @endif
    </span>
@else
<div class="claim-status" data-claim-status="{{ $workflow['label'] }}">
    @if($showBadge)
        <span class="badge badge-{{ $workflow['tone'] }} claim-status-badge">{{ $workflow['label'] }}</span>
    @endif
    <p class="claim-status-message">{{ $workflow['message'] }}</p>
    @if($claim->reviewer)
        <p class="claim-status-reviewer">Reviewed by {{ $claim->reviewer->name }}</p>
    @endif
</div>
@endif
