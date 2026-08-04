@extends('layouts.app')

@section('title', 'Purchase Requests')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Purchase Requests</h1>
        <p>View and manage Pantry, General, and Lunch claims.</p>
    </div>
    @role('Stocker')
        <a href="{{ route('tuntutan.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>Submit a request</span>
        </a>
    @endrole
</div>

<x-tuntutan-week-filter
    :calendar-month="$calendarMonth"
    :calendar-weeks="$calendarWeeks"
    :selected-weeks="$selectedWeeks"
    :selected-type="$selectedType"
    :selected-status="$selectedStatus"
/>

@forelse($claimsGrouped as $week => $claims)
    @php
        $startOfWeek = '-';
        $endOfWeek = '-';

        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches)) {
            $weekDate = \Carbon\Carbon::now()->setISODate((int) $matches[1], (int) $matches[2]);
            $startOfWeek = $weekDate->startOfWeek()->format('d/m/Y');
            $endOfWeek = $weekDate->endOfWeek()->format('d/m/Y');
        }
    @endphp
    <section class="card claims-week-card">
        <header class="card-header-flex claims-week-header">
            <div>
                <h2>Week: {{ $week }}</h2>
                <p>Dates: {{ $startOfWeek }} to {{ $endOfWeek }}</p>
            </div>
        </header>

        <div class="table-wrapper claims-desktop-table">
            <table class="custom-table claims-table {{ Auth::user()->hasRole('Superadmin') ? 'claims-table-with-actions' : '' }}">
                <colgroup>
                    <col class="claims-requester-column">
                    <col class="claims-type-column">
                    <col class="claims-details-column">
                    <col class="claims-dates-column">
                    <col class="claims-amount-column">
                    <col class="claims-status-column">
                    @role('Superadmin')
                        <col class="claims-actions-column">
                    @endrole
                </colgroup>
                <thead>
                    <tr>
                        <th>Requester</th>
                        <th>Type</th>
                        <th class="claim-details-header">Request details</th>
                        <th>Dates</th>
                        <th>Amount</th>
                        <th class="claim-status-cell">Status</th>
                        @role('Superadmin')
                            <th class="claim-actions-cell">Superadmin action</th>
                        @endrole
                    </tr>
                </thead>
                <tbody>
                    @foreach($claims as $claim)
                        @php
                            $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
                            $requestDate = $claim->request_date ?? $claim->tarikh_beli;
                        @endphp
                        <tr>
                            <td>
                                <div class="table-item-info">
                                    <strong>{{ $claim->requestor_name ?: $claim->user->name }}</strong>
                                    <div class="table-secondary-text">{{ $claim->user->email }}</div>
                                </div>
                            </td>
                            <td><x-tuntutan-type-badge :claim="$claim" /></td>
                            <td class="claim-details-cell"><x-tuntutan-details :claim="$claim" context="desktop" /></td>
                            <td class="claim-dates-cell">
                                @if($claim->isPurchaseRequest())
                                    <div><span>Requested</span><strong>{{ $requestDate?->format('d/m/Y') ?? '-' }}</strong></div>
                                    <div><span>Received</span><strong>{{ $claim->date_receive?->format('d/m/Y') ?? '-' }}</strong></div>
                                @else
                                    <strong>{{ $claim->tarikh_beli?->format('d/m/Y') ?? '-' }}</strong>
                                @endif
                            </td>
                            <td class="claim-value-cell"><strong>RM {{ number_format($amount, 2) }}</strong></td>
                            <td class="claim-status-cell"><x-tuntutan-status :claim="$claim" /></td>
                            @role('Superadmin')
                                <td class="claim-actions-cell"><x-tuntutan-actions :claim="$claim" /></td>
                            @endrole
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="claims-mobile-list">
            @foreach($claims as $claim)
                @php($modalId = "claim-mobile-modal-{$claim->id}")
                <article class="claim-mobile-card">
                    <x-tuntutan-mobile-summary :claim="$claim" :modal-id="$modalId" />
                    <x-tuntutan-mobile-dialog :claim="$claim" :modal-id="$modalId" />
                </article>
            @endforeach
        </div>
    </section>
@empty
    <div class="card claims-empty-state">
        <i class="fa-solid fa-file-circle-plus"></i>
        No purchase requests found.
    </div>
@endforelse

<script>
    (() => {
        document.querySelectorAll('[data-claim-modal-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const dialog = document.getElementById(trigger.dataset.claimModalOpen);

                if (!(dialog instanceof HTMLDialogElement) || dialog.open) {
                    return;
                }

                dialog.claimModalTrigger = trigger;
                dialog.showModal();
                dialog.querySelector('[data-claim-modal-close]')?.focus();
            });
        });

        document.querySelectorAll('.claim-mobile-modal').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            dialog.addEventListener('close', () => {
                dialog.claimModalTrigger?.focus();
                dialog.claimModalTrigger = null;
            });
        });

        document.querySelectorAll('[data-claim-modal-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('.claim-mobile-modal')?.close());
        });

        document.querySelectorAll('[data-attachment-open-link]').forEach((link) => {
            link.addEventListener('click', () => {
                if (link.dataset.attachmentOpening === 'true') {
                    return;
                }

                link.dataset.attachmentOpening = 'true';
                link.classList.add('is-opening');
                link.querySelector('[data-attachment-open-label]')?.replaceChildren('Opening attachment...');
                link.parentElement?.querySelector('[data-attachment-open-status]')?.replaceChildren('Opening attachment in a new tab.');
            });
        });
    })();
</script>
@endsection
