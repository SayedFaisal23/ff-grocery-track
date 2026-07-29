@extends('layouts.app')

@section('title', 'Senarai Inventori')

@section('content')
<style>
    /* Mobile responsive optimizations for Collapsible Cards */
    @media (max-width: 768px) {
        .mobile-item-card {
            cursor: pointer;
        }
        .mobile-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            transition: all 0.2s ease-in-out;
        }
        .mobile-item-card.collapsed .mobile-card-body {
            display: none !important;
        }
        .mobile-item-card.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .mobile-card-stats {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 8px !important;
            text-align: center !important;
        }
        .stat-box {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
        }
        .stat-box .stat-label {
            white-space: nowrap !important;
            font-size: 0.65rem !important;
            letter-spacing: 0.2px !important;
        }
    }
</style>
<div class="page-header">
    <div class="page-title">
        <h1>Inventori Barang Runcit</h1>
        <p>Uruskan baki unit dan status barangan dapur</p>
    </div>
    @hasanyrole('Superadmin|Stocker|Tracker')
    <a href="{{ route('inventori.create') }}" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i>
        <span>Tambah Barang</span>
    </a>
    @endhasanyrole
</div>

<!-- Penapis dan Carian -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form action="{{ route('inventori.index') }}" method="GET" class="inventori-filter-form">
        <div class="inventori-search-row">
            <div class="inventori-search-input">
                <input type="text" name="carian" class="form-control" placeholder="Cari Nama/Jenama..." value="{{ request('carian') }}">
            </div>
            <button type="submit" class="btn btn-secondary inventori-submit-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span class="tapis-label">Tapis</span>
            </button>
        </div>
        <div class="inventori-filter-row">
            <select name="kategori" class="form-control">
                <option value="">Semua Kategori</option>
                @foreach($kategoriSenarai as $kat)
                    <option value="{{ $kat->id }}" {{ (string) request('kategori') === (string) $kat->id ? 'selected' : '' }}>{{ $kat->nama }}</option>
                @endforeach
            </select>
            @if(request('carian') || request('kategori'))
                <a href="{{ route('inventori.index') }}" class="btn btn-secondary" style="background: transparent; border: none; white-space: nowrap;">Set Semula</a>
            @endif
        </div>
    </form>
</div>

<!-- Senarai Barang -->
<div class="card inventori-list-card" style="padding: 0;">
    <div class="table-wrapper desktop-only-view">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 60px;">No.</th>
                    <th>Nama/Jenama</th>
                    <th>Kategori</th>
                    <th>Baki</th>
                    <th>Tarikh Luput</th>
                    <th style="text-align: right;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td data-label="No.">
                        <span style="color: var(--text-dark); font-weight: 500;">{{ $loop->iteration }}</span>
                    </td>
                    <td data-label="Nama Item">
                        <div class="table-item-info">
                            <div style="font-weight: 600; font-size: 1rem; color: #fff;">{{ $item->nama_item }}</div>
                            @if($item->jenis || $item->capacity)
                                <div style="font-size: 0.78rem; color: var(--text-dark); margin-top: 2px;">
                                    @if($item->jenis)<strong>{{ $item->jenis }}</strong>@endif
                                    @if($item->jenis && $item->capacity)<span> • </span>@endif
                                    @if($item->capacity)<strong>{{ $item->capacity }}</strong>@endif
                                </div>
                            @endif
                        </div>
                    </td>
                    <td data-label="Kategori">
                        <x-kategori-pill :kategori="$item->kategoriPreset" />
                    </td>
                    <td data-label="Baki">
                        @if($item->jumlah_belum_dibuka == 0)
                            <span class="badge badge-danger">0 Unit (Habis Stok)</span>
                        @else
                            <strong style="color: #fff;">{{ $item->jumlah_belum_dibuka }}</strong> unit
                        @endif
                    </td>
                    <td data-label="Tarikh Luput">
                        @if($item->jejak_luput && $item->tarikh_luput)
                            @php
                                $daysToExpiry = now()->startOfDay()->diffInDays($item->tarikh_luput->startOfDay(), false);
                            @endphp
                            @if($daysToExpiry < 0)
                                <div><span class="badge badge-danger">Telah Luput ({{ abs($daysToExpiry) }} hari)</span></div>
                                <div style="font-size: 0.85rem; color: var(--color-danger); margin-top: 4px; font-weight: 500;">{{ $item->tarikh_luput->format('d/m/Y') }}</div>
                            @elseif($daysToExpiry <= 3)
                                <div><span class="badge badge-warning">Hampir Luput ({{ $daysToExpiry }} hari)</span></div>
                                <div style="font-size: 0.85rem; color: var(--color-warning); margin-top: 4px; font-weight: 500;">{{ $item->tarikh_luput->format('d/m/Y') }}</div>
                            @else
                                <span style="font-size: 0.9rem; color: var(--text-muted);">{{ $item->tarikh_luput->format('d/m/Y') }}</span>
                            @endif
                        @else
                            <span style="font-size: 0.85rem; color: var(--text-dark);">Tidak dijejak</span>
                        @endif
                    </td>
                    <td data-label="Tindakan" style="text-align: right;">
                        <div style="display: inline-flex; gap: 8px;">
                            <button onclick="bukaModalPelarasan({{ json_encode($item) }})" class="btn btn-secondary btn-sm" title="Selaraskan Stok">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-secondary btn-sm" title="Edit Barangan">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            @hasanyrole('Superadmin|Stocker|Tracker')
                            <form action="{{ route('inventori.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Adakah anda pasti mahu memadam item ini?')" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="Padam Barangan" style="background-color: transparent; color: var(--color-danger); border: 1px solid rgba(239, 68, 68, 0.2);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            @endhasanyrole
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        <i class="fa-solid fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: var(--text-dark);"></i>
                        Tiada rekod inventori dijumpai.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- View Mudah Alih / Mobile View -->
    <div class="mobile-only-view">
        @forelse($items as $item)
        <div class="mobile-item-card collapsed" id="mobile-card-{{ $item->id }}">
            <div class="mobile-card-header" onclick="toggleMobileCard({{ $item->id }})" style="cursor: pointer; -webkit-tap-highlight-color: transparent;">
                <div class="item-name-group">
                    <span class="item-name" style="display: flex; align-items: center; gap: 4px;">
                        {{ $item->nama_item }}
                        <i class="fa-solid fa-chevron-down toggle-icon" style="font-size: 0.85rem; color: var(--text-dark); transition: transform 0.2s;"></i>
                    </span>
                    @if($item->jenis || $item->capacity)
                        <span style="font-size: 0.72rem; color: var(--text-dark); margin-top: 3px;">
                            @if($item->jenis)<strong>{{ $item->jenis }}</strong>@endif
                            @if($item->jenis && $item->capacity) • @endif
                            @if($item->capacity)<strong>{{ $item->capacity }}</strong>@endif
                        </span>
                    @endif
                </div>
                <div class="item-expiry" style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                    @if($item->jejak_luput && $item->tarikh_luput)
                        @php
                            $daysToExpiry = now()->startOfDay()->diffInDays($item->tarikh_luput->startOfDay(), false);
                        @endphp
                        @if($daysToExpiry < 0)
                            <span class="badge badge-danger">Telah Luput ({{ abs($daysToExpiry) }}h)</span>
                            <div style="font-size: 0.75rem; color: var(--color-danger); text-align: right;">{{ $item->tarikh_luput->format('d/m/Y') }}</div>
                        @elseif($daysToExpiry <= 3)
                            <span class="badge badge-warning">Hampir Luput ({{ $daysToExpiry }}h)</span>
                            <div style="font-size: 0.75rem; color: var(--color-warning); text-align: right;">{{ $item->tarikh_luput->format('d/m/Y') }}</div>
                        @else
                            <span class="expiry-date-text" style="font-size: 0.85rem; color: var(--text-muted);">EXP: {{ $item->tarikh_luput->format('d/m/Y') }}</span>
                        @endif
                    @else
                        <span class="expiry-no-track" style="font-size: 0.8rem; color: var(--text-dark);">Tidak dijejak</span>
                    @endif
                    <x-kategori-pill :kategori="$item->kategoriPreset" />
                </div>
            </div>
            <div class="mobile-card-stats">
                <div class="stat-box">
                    <span class="stat-label">Baki</span>
                    <span class="stat-val">
                        @if($item->jumlah_belum_dibuka == 0)
                            <span class="badge badge-danger" style="padding: 2px 6px; font-size: 0.7rem; font-weight: 500;">0 Unit</span>
                        @else
                            <strong>{{ $item->jumlah_belum_dibuka }}</strong> unit
                        @endif
                    </span>
                </div>
            </div>

            <div class="mobile-card-body">
                <div class="mobile-card-actions">
                    <div></div>
                    <div class="action-buttons">
                        <button onclick="bukaModalPelarasan({{ json_encode($item) }})" class="btn btn-secondary btn-sm" title="Selaraskan Stok">
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                        <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-secondary btn-sm" title="Edit Barangan">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        @hasanyrole('Superadmin|Stocker|Tracker')
                        <form action="{{ route('inventori.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Adakah anda pasti mahu memadam item ini?')" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Padam Barangan" style="background-color: transparent; color: var(--color-danger); border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 10px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                        @endhasanyrole
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="mobile-empty-state">
            <i class="fa-solid fa-box-open" style="font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: var(--text-dark);"></i>
            Tiada rekod inventori dijumpai.
        </div>
        @endforelse
    </div>
</div>

<!-- Modal Pelarasan Tahap Stok -->
<div id="modalPelarasan" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 460px; margin: 1rem; box-shadow: var(--shadow-lg);">
        <div class="card-header-flex">
            <h3 id="modalTitle" style="color: #fff; font-size: 1.25rem;">Selaraskan Stok</h3>
            <button onclick="tutupModalPelarasan()" style="cursor: pointer; font-size: 1.25rem;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <form id="formPelarasan" method="POST">
            @csrf
            @method('PUT')
            
            <input type="hidden" name="peratus_baki" id="adj_peratus" value="100">

            <div class="form-group">
                <label class="form-label">Baki (Unit)</label>
                <input type="number" name="jumlah_belum_dibuka" id="adj_belum_dibuka" class="form-control" min="0" required>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 2rem;">
                <button type="button" onclick="tutupModalPelarasan()" class="btn btn-secondary">Batal</button>
                <button type="submit" class="btn btn-success">Simpan Pelarasan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleMobileCard(itemId) {
        const card = document.getElementById('mobile-card-' + itemId);
        if (card) {
            card.classList.toggle('collapsed');
        }
    }

    function bukaModalPelarasan(item) {
        document.getElementById('modalTitle').innerText = 'Selaraskan: ' + item.nama_item;
        document.getElementById('formPelarasan').action = '/inventori/' + item.id + '/adjust';
        document.getElementById('adj_belum_dibuka').value = item.jumlah_belum_dibuka;
        document.getElementById('adj_peratus').value = item.peratus_baki;

        document.getElementById('modalPelarasan').style.display = 'flex';
    }
    
    function tutupModalPelarasan() {
        document.getElementById('modalPelarasan').style.display = 'none';
    }
</script>
@endsection
