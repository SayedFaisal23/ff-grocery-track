@extends('layouts.app')

@section('title', 'Permohonan Pembelian')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Permohonan Pembelian</h1>
        <p>Lihat dan urus permohonan Pantry, General, dan tuntutan Lunch</p>
    </div>
    @role('Stocker')
        <a href="{{ route('tuntutan.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>Hantar Permohonan</span>
        </a>
    @endrole
</div>

@forelse($claimsGrouped as $week => $claims)
    @php
        $totalWeek = $claims->sum(fn ($claim) => $claim->total_item_amount ?? $claim->nilai_tuntutan);
        $startOfWeek = '-';
        $endOfWeek = '-';

        if (preg_match('/^(\d{4})-W(\d{2})$/', $week, $matches)) {
            $weekDate = \Carbon\Carbon::now()->setISODate((int) $matches[1], (int) $matches[2]);
            $startOfWeek = $weekDate->startOfWeek()->format('d/m/Y');
            $endOfWeek = $weekDate->endOfWeek()->format('d/m/Y');
        }
    @endphp
    <div class="card" style="margin-bottom: 2rem; border: 1px solid rgba(99, 102, 241, 0.2);">
        <div class="card-header-flex" style="border-bottom-color: rgba(99, 102, 241, 0.2);">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #fff;">Minggu: {{ $week }}</h2>
                <small style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">Tarikh: {{ $startOfWeek }} hingga {{ $endOfWeek }}</small>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Jumlah Amaun</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success);">RM {{ number_format($totalWeek, 2) }}</div>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Pemohon</th>
                        <th>Jenis</th>
                        <th>Butiran Permohonan</th>
                        <th>Tarikh</th>
                        <th>Amaun</th>
                        <th class="claim-status-cell">Status</th>
                        @role('Superadmin')
                            <th style="text-align: right;">Tindakan Superadmin</th>
                        @endrole
                    </tr>
                </thead>
                <tbody>
                    @foreach($claims as $claim)
                        @php
                            $isPurchaseRequest = in_array($claim->tag, ['Pantry', 'General'], true);
                            $amount = $claim->total_item_amount ?? $claim->nilai_tuntutan;
                            $requestDate = $claim->request_date ?? $claim->tarikh_beli;
                        @endphp
                        <tr>
                            <td data-label="Pemohon">
                                <div class="table-item-info">
                                    <strong>{{ $claim->requestor_name ?: $claim->user->name }}</strong>
                                    <div style="font-size: 0.75rem; color: var(--text-dark);">{{ $claim->user->email }}</div>
                                </div>
                            </td>
                            <td data-label="Jenis">
                                @if($claim->tag === 'Pantry')
                                    <span class="badge badge-primary"><i class="fa-solid fa-boxes-stacked"></i> Pantry</span>
                                @elseif($claim->tag === 'General')
                                    <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;"><i class="fa-solid fa-folder-open"></i> General</span>
                                @else
                                    <span class="badge badge-success"><i class="fa-solid fa-utensils"></i> Lunch</span>
                                @endif
                            </td>
                            <td data-label="Butiran Permohonan">
                                <strong>{{ $isPurchaseRequest ? ($claim->item_specification ?: $claim->nama_item) : $claim->nama_item }}</strong>
                                @if($isPurchaseRequest)
                                    <div style="margin-top: 6px; font-size: 0.82rem; color: var(--text-muted); line-height: 1.45;">
                                        <div><span style="color: var(--text-dark);">Tujuan:</span> {{ $claim->purchase_purpose }}</div>
                                        @if($claim->invoice_no)<div><span style="color: var(--text-dark);">Invois:</span> {{ $claim->invoice_no }}</div>@endif
                                        <div><span style="color: var(--text-dark);">Platform:</span> {{ $claim->purchase_platform }}</div>
                                        <div><span style="color: var(--text-dark);">Bayaran:</span> {{ $claim->payment_method }}</div>
                                        <div><span style="color: var(--text-dark);">Invois ke akaun:</span> {{ $claim->invoice_sent_to_account ? 'Ya' : 'Tidak' }}</div>
                                    </div>
                                @endif
                                @if($claim->attachment)
                                    <div style="margin-top: 7px;">
                                        <a href="{{ route('tuntutan.attachment', $claim) }}" target="_blank" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 0.72rem;">
                                            <i class="fa-solid fa-paperclip"></i> Dokumen sokongan
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td data-label="Tarikh">
                                @if($isPurchaseRequest)
                                    <div><span style="font-size: .75rem; color: var(--text-dark);">Mohon</span><br>{{ $requestDate->format('d/m/Y') }}</div>
                                    <div style="margin-top: 6px;"><span style="font-size: .75rem; color: var(--text-dark);">Terima</span><br>{{ $claim->date_receive?->format('d/m/Y') ?? '-' }}</div>
                                @else
                                    {{ $claim->tarikh_beli->format('d/m/Y') }}
                                @endif
                            </td>
                            <td data-label="Amaun" class="claim-value-cell"><strong>RM {{ number_format($amount, 2) }}</strong></td>
                            <td data-label="Status" class="claim-status-cell">
                                @if($claim->status === 'Pending')
                                    <span class="badge badge-warning claim-status-badge">Pending</span>
                                @else
                                    <span class="badge badge-success claim-status-badge">Completed</span>
                                    @if($claim->approval_result === 'Approved')
                                        <div style="margin-top: 5px;"><span class="badge badge-success">Approved</span></div>
                                    @elseif($claim->approval_result === 'Rejected')
                                        <div style="margin-top: 5px;"><span class="badge badge-danger">Rejected</span></div>
                                    @endif
                                    @if($claim->reviewer)
                                        <div style="font-size: 0.72rem; color: var(--text-dark); margin-top: 5px;">{{ $claim->reviewer->name }}</div>
                                    @endif
                                @endif
                            </td>
                            @role('Superadmin')
                                <td data-label="Tindakan Superadmin" style="text-align: right;">
                                    @if($claim->status === 'Pending')
                                        <div style="display: inline-flex; gap: 8px;">
                                            <form action="{{ route('tuntutan.status', $claim) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="approval_result" value="Approved">
                                                <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Lulus</button>
                                            </form>
                                            <form action="{{ route('tuntutan.status', $claim) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="approval_result" value="Rejected">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-xmark"></i> Tolak</button>
                                            </form>
                                        </div>
                                    @else
                                        <span style="font-size: 0.85rem; color: var(--text-dark);">Status dikunci</span>
                                    @endif
                                </td>
                            @endrole
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="card" style="text-align: center; color: var(--text-muted); padding: 4rem;">
        <i class="fa-solid fa-file-circle-plus" style="font-size: 4rem; color: var(--text-dark); margin-bottom: 1.5rem; display: block;"></i>
        Tiada permohonan pembelian dijumpai.
    </div>
@endforelse
@endsection
