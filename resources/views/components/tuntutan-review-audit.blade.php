@props(['claim'])

@role('Superadmin')
    @php
        $formatAuditDate = static fn ($timestamp, string $fallback = 'Not opened yet'): string => $timestamp?->copy()
            ->timezone('Asia/Kuala_Lumpur')
            ->format('d/m/Y, H:i') ?? $fallback;
    @endphp

    <dl class="claim-review-audit" data-claim-audit-for="{{ $claim->id }}">
        <div>
            <dt>Latest attachment download:</dt>
            <dd data-latest-attachment-downloaded-at>{{ $formatAuditDate($claim->latest_attachment_downloaded_at) }}</dd>
        </div>
    </dl>
@endrole
