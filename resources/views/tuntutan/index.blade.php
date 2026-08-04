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
            <table class="custom-table claims-table">
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
                            <td class="claim-details-cell"><x-tuntutan-details :claim="$claim" /></td>
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
                @php
                    $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
                    $requestDate = $claim->request_date ?? $claim->tarikh_beli;
                @endphp
                <article class="claim-mobile-card">
                    <header class="claim-mobile-card-header">
                        <div>
                            <span class="claim-mobile-requester">{{ $claim->requestor_name ?: $claim->user->name }}</span>
                            <span class="claim-mobile-email">{{ $claim->user->email }}</span>
                        </div>
                        <x-tuntutan-type-badge :claim="$claim" />
                    </header>

                    <x-tuntutan-details :claim="$claim" />

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
@endsection
