@extends('layouts.app')

@section('title', 'Tuntutan Pembelian')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Tuntutan Pembelian</h1>
        <p>Lihat dan urus tuntutan mingguan bagi pembelian barangan groseri</p>
    </div>
    @role('Stocker')
    <a href="{{ route('tuntutan.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Hantar Tuntutan</span>
    </a>
    @endrole
</div>

@forelse($claimsGrouped as $week => $claims)
    @php
        // Kira jumlah nilai tuntutan untuk minggu ini
        $totalWeek = $claims->sum('nilai_tuntutan');

        // Hitung julat tarikh untuk minggu ini
        try {
            $carbonWeek = \Carbon\Carbon::parse($week);
            $startOfWeek = $carbonWeek->startOfWeek()->format('d/m/Y');
            $endOfWeek = $carbonWeek->endOfWeek()->format('d/m/Y');
        } catch (\Exception $e) {
            $startOfWeek = '-';
            $endOfWeek = '-';
        }
    @endphp
    <div class="card" style="margin-bottom: 2rem; border: 1px solid rgba(99, 102, 241, 0.2);">
        <div class="card-header-flex" style="border-bottom-color: rgba(99, 102, 241, 0.2);">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #fff;">
                    Minggu: {{ $week }}
                </h2>
                <small style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">Tarikh: {{ $startOfWeek }} hingga {{ $endOfWeek }}</small>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: var(--text-muted);">Jumlah Tuntutan Minggu Ini</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success);">RM {{ number_format($totalWeek, 2) }}</div>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Pencadang</th>
                        <th>Jenis</th>
                        <th>Barang Pembelian</th>
                        <th>Tarikh Beli</th>
                        <th>Nilai Tuntutan</th>
                        <th class="claim-status-cell">Status</th>
                        @role('Superadmin')
                        <th style="text-align: right;">Tindakan Superadmin</th>
                        @endrole
                    </tr>
                </thead>
                <tbody>
                    @foreach($claims as $claim)
                    <tr>
                        <td data-label="Pencadang">
                            <div class="table-item-info">
                                <strong>{{ $claim->user->name }}</strong>
                                <div style="font-size: 0.75rem; color: var(--text-dark);">{{ $claim->user->email }}</div>
                            </div>
                        </td>
                        <td data-label="Jenis">
                            @if($claim->tag === 'Stok')
                                <span class="badge badge-primary" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-boxes-stacked" style="font-size: 0.7rem;"></i> Stok
                                </span>
                            @elseif($claim->tag === 'General')
                                <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-folder-open" style="font-size: 0.7rem;"></i> General
                                </span>
                            @elseif($claim->tag === 'Food')
                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fcd34d; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-bowl-food" style="font-size: 0.7rem;"></i> Food
                                </span>
                            @elseif($claim->tag === 'Lunch')
                                <span class="badge badge-success" style="display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-solid fa-seedling" style="font-size: 0.7rem;"></i> Lunch
                                </span>
                            @else
                                <span style="font-size: 0.8rem; color: var(--text-dark);">{{ $claim->tag }}</span>
                            @endif
                        </td>
                        <td data-label="Barang Pembelian">
                            {{ $claim->nama_item }}
                            @if($claim->attachment)
                                <div style="margin-top: 4px;">
                                    <a href="{{ route('tuntutan.attachment', $claim) }}" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; font-size: 0.72rem; border-radius: 4px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.05); color: var(--text-main);">
                                        <i class="fa-solid fa-paperclip" style="font-size: 0.7rem;"></i> Resit / Lampiran
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td data-label="Tarikh Beli">{{ $claim->tarikh_beli->format('d/m/Y') }}</td>
                        <td data-label="Nilai Tuntutan" class="claim-value-cell"><strong>RM {{ number_format($claim->nilai_tuntutan, 2) }}</strong></td>
                        <td data-label="Status" class="claim-status-cell">
                            @if($claim->status === 'Selesai')
                                <span class="badge badge-success claim-status-badge">Selesai (Dibayar)</span>
                            @elseif($claim->status === 'Ditolak')
                                <span class="badge badge-danger claim-status-badge">Ditolak</span>
                            @else
                                <span class="badge badge-warning claim-status-badge">Dalam Proses</span>
                            @endif
                        </td>
                        @role('Superadmin')
                        <td data-label="Tindakan Superadmin" style="text-align: right;">
                            @if($claim->status === 'Dalam Proses')
                            <div style="display: inline-flex; gap: 8px;">
                                <form action="{{ route('tuntutan.status', $claim->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="Selesai">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fa-solid fa-check"></i> Lulus
                                    </button>
                                </form>
                                <form action="{{ route('tuntutan.status', $claim->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="Ditolak">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fa-solid fa-xmark"></i> Tolak
                                    </button>
                                </form>
                            </div>
                            @else
                                <span style="font-size: 0.85rem; color: var(--text-dark);">Status Dikunci</span>
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
        <i class="fa-solid fa-receipt" style="font-size: 4rem; color: var(--text-dark); margin-bottom: 1.5rem; display: block;"></i>
        Tiada sebarang tuntutan pembayaran dijumpai.
    </div>
@endforelse
@endsection
