@props(['claim', 'compact' => false])

@php($workflow = $claim->workflowStatus())

@if($compact)
    <span class="claim-status claim-status-compact" data-claim-status="{{ $workflow['label'] }}">
        <span class="badge badge-{{ $workflow['tone'] }} claim-status-badge">{{ $workflow['label'] }}</span>
    </span>
@else
<div class="claim-status" data-claim-status="{{ $workflow['label'] }}">
    <span class="badge badge-{{ $workflow['tone'] }} claim-status-badge">{{ $workflow['label'] }}</span>
    <p class="claim-status-message">{{ $workflow['message'] }}</p>
    @if($claim->reviewer)
        <p class="claim-status-reviewer">Reviewed by {{ $claim->reviewer->name }}</p>
    @endif
</div>
@endif
