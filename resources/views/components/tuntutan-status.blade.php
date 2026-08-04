@props(['claim'])

@php($workflow = $claim->workflowStatus())

<div class="claim-status" data-claim-status="{{ $workflow['label'] }}">
    <span class="badge badge-{{ $workflow['tone'] }} claim-status-badge">{{ $workflow['label'] }}</span>
    <p class="claim-status-message">{{ $workflow['message'] }}</p>
    @if($claim->reviewer)
        <p class="claim-status-reviewer">Reviewed by {{ $claim->reviewer->name }}</p>
    @endif
</div>
