@extends('layouts.app')

@section('title', 'Senarai Inventori')

@section('content')
<style>
    /* Mobile responsive inventory cards */
    @media (max-width: 768px) {
        .mobile-item-card-trigger {
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .mobile-item-card-trigger:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 3px;
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

<section class="inventory-summary" aria-labelledby="inventory-summary-title">
    <div class="inventory-summary-heading">
        <h2 id="inventory-summary-title">Ringkasan Inventori</h2>
        <span>Gambaran keseluruhan stok</span>
    </div>
    <div class="grid-stats inventory-summary-grid">
        <article class="card-stat">
            <div class="inventory-stat-label">
                <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                <span>Jumlah Item</span>
            </div>
            <div class="stat-value">{{ number_format($inventorySummary['totalItems']) }}</div>
            <span class="inventory-stat-description">Item berdaftar</span>
        </article>
        <article class="card-stat success">
            <div class="inventory-stat-label">
                <i class="fa-solid fa-cubes-stacked" aria-hidden="true"></i>
                <span>Jumlah Unit</span>
            </div>
            <div class="stat-value">{{ number_format($inventorySummary['totalUnits']) }}</div>
            <span class="inventory-stat-description">Unit tersedia</span>
        </article>
        <article class="card-stat danger">
            <div class="inventory-stat-label">
                <i class="fa-solid fa-calendar-xmark" aria-hidden="true"></i>
                <span>Sudah Luput</span>
            </div>
            <div class="stat-value">{{ number_format($inventorySummary['expired']) }}</div>
            <span class="inventory-stat-description">Perlu tindakan segera</span>
        </article>
        <article class="card-stat warning">
            <div class="inventory-stat-label">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>Bawah Had</span>
            </div>
            <div class="stat-value">{{ number_format($inventorySummary['belowThreshold']) }}</div>
            <span class="inventory-stat-description">Stok hampir habis</span>
        </article>
        <article class="card-stat danger inventory-summary-card-wide">
            <div class="inventory-stat-label">
                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                <span>Habis Stok</span>
            </div>
            <div class="stat-value">{{ number_format($inventorySummary['outOfStock']) }}</div>
            <span class="inventory-stat-description">Perlu restok segera</span>
        </article>
    </div>
</section>

<!-- Penapis dan Carian -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form action="{{ route('inventori.index') }}" method="GET" class="inventori-filter-form">
        @if($activeSort)
            <input type="hidden" name="sort" value="{{ $activeSort }}">
        @endif
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
            @if(request('carian') || request('kategori') || $activeSort)
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
                    <x-inventori-sort-header sort="nama" label="Nama/Jenama" :active-sort="$activeSort" />
                    <x-inventori-sort-header sort="kategori" label="Kategori" :active-sort="$activeSort" />
                    <x-inventori-sort-header sort="baki" label="Baki" :active-sort="$activeSort" alignment="center" />
                    <x-inventori-sort-header sort="tarikh_luput" label="Tarikh Luput" :active-sort="$activeSort" />
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
                            <div class="inventory-item-name">{{ $item->nama_item }}</div>
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
                    <td data-label="Baki" class="inventory-balance-cell">
                        @if($item->jumlah_belum_dibuka == 0)
                            <span class="badge badge-danger inventory-empty-stock-badge"><strong>0</strong><span class="inventory-unit-label">unit</span></span>
                        @else
                            <strong class="inventory-balance-value">{{ $item->jumlah_belum_dibuka }}</strong> <span class="inventory-unit-label">unit</span>
                        @endif
                    </td>
                    <td data-label="Tarikh Luput">
                        @if($item->jejak_luput && $item->tarikh_luput)
                            @php
                                $daysToExpiry = now()->startOfDay()->diffInDays($item->tarikh_luput->startOfDay(), false);
                            @endphp
                            @if($daysToExpiry < 0)
                                <span class="expiry-date-urgent expiry-date-expired">{{ $item->tarikh_luput->format('d/m/Y') }}</span>
                            @elseif($daysToExpiry <= 3)
                                <div class="inventory-expiry-warning">
                                    <span class="badge badge-warning">Hampir Luput ({{ $daysToExpiry }} hari)</span>
                                    <span class="inventory-expiry-warning-date">{{ $item->tarikh_luput->format('d/m/Y') }}</span>
                                </div>
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
                                <i class="fa-solid fa-pen-to-square"></i>
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
        <div
            class="mobile-item-card mobile-item-card-trigger"
            role="button"
            tabindex="0"
            aria-haspopup="dialog"
            aria-controls="modalPelarasan"
            aria-label="Selaraskan stok {{ $item->nama_item }}"
            onclick="bukaModalPelarasan({{ json_encode($item) }}, this)"
            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); bukaModalPelarasan({{ json_encode($item) }}, this); }"
        >
            <div class="mobile-card-header">
                <div class="item-name-group">
                    <span class="item-name">
                        {{ $item->nama_item }}
                    </span>
                    @if($item->jenis || $item->capacity)
                        <span class="item-variant-line">
                            @if($item->jenis)<strong>{{ $item->jenis }}</strong>@endif
                            @if($item->jenis && $item->capacity) • @endif
                            @if($item->capacity)<strong>{{ $item->capacity }}</strong>@endif
                        </span>
                    @else
                        <span class="item-variant-line item-variant-line-placeholder" aria-hidden="true">&nbsp;</span>
                    @endif
                </div>
                <div class="item-expiry">
                    @if($item->jejak_luput && $item->tarikh_luput)
                        @php
                            $daysToExpiry = now()->startOfDay()->diffInDays($item->tarikh_luput->startOfDay(), false);
                            $expiryDateClass = 'expiry-date-text';

                            if ($daysToExpiry < 0) {
                                $expiryDateClass .= ' expiry-date-urgent expiry-date-expired';
                            } elseif ($daysToExpiry <= 3) {
                                $expiryDateClass .= ' expiry-date-urgent expiry-date-warning';
                            } else {
                                $expiryDateClass .= ' expiry-date-text-muted';
                            }
                        @endphp
                        <span class="expiry-date-label">EXP:</span>
                        <span class="{{ $expiryDateClass }}">{{ $item->tarikh_luput->format('d/m/Y') }}</span>
                    @else
                        <span class="expiry-date-label">EXP:</span>
                        <span class="expiry-no-track">-</span>
                    @endif
                    <x-kategori-pill :kategori="$item->kategoriPreset" />
                </div>
            </div>
            <div class="mobile-card-stats">
                <div class="stat-box">
                    <span class="stat-label">Baki</span>
                    <span class="stat-val">
                        @if($item->jumlah_belum_dibuka == 0)
                            <span class="badge badge-danger inventory-empty-stock-badge"><strong>0</strong><span class="inventory-unit-label">unit</span></span>
                        @else
                            <strong>{{ $item->jumlah_belum_dibuka }}</strong><span class="inventory-unit-label">unit</span>
                        @endif
                    </span>
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
<div id="modalPelarasan" class="modal-overlay" aria-hidden="true" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card adjustment-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle" style="width: 100%; max-width: 460px; margin: 1rem; box-shadow: var(--shadow-lg);">
        <div class="adjustment-modal-header">
            <h3 id="modalTitle" style="color: #fff; font-size: 1.25rem;">Selaraskan Stok</h3>
            <button type="button" onclick="tutupModalPelarasan()" class="adjustment-modal-close" aria-label="Tutup popup pelarasan">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form id="formPelarasan" method="POST">
            @csrf
            @method('PUT')
            
            <input type="hidden" name="peratus_baki" id="adj_peratus" value="100">

            <div class="form-group">
                <label class="form-label">Baki (Unit)</label>
                <input type="number" name="jumlah_belum_dibuka" id="adj_belum_dibuka" class="form-control" min="0" required>
            </div>
        </form>

        <div class="adjustment-modal-secondary-actions">
            <a id="modalEditLink" href="#" class="btn btn-secondary">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Edit<br>Barangan</span>
            </a>
        </div>

        <div class="adjustment-modal-primary-actions">
            <button type="submit" form="formPelarasan" class="btn btn-success">
                <i class="fa-solid fa-check"></i>
                <span>Simpan<br>Pelarasan</span>
            </button>
            @hasanyrole('Superadmin|Stocker|Tracker')
            <form id="modalDeleteForm" method="POST" onsubmit="return confirm('Adakah anda pasti mahu memadam item ini?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-trash"></i>
                    <span>Padam<br>Barangan</span>
                </button>
            </form>
            @endhasanyrole
        </div>
    </div>
</div>

<script>
    let modalTriggerElement = null;

    function bukaModalPelarasan(item, triggerElement = null) {
        const modal = document.getElementById('modalPelarasan');

        modalTriggerElement = triggerElement || document.activeElement;
        document.getElementById('modalTitle').innerText = 'Selaraskan: ' + item.nama_item;
        document.getElementById('formPelarasan').action = '/inventori/' + item.id + '/adjust';
        document.getElementById('adj_belum_dibuka').value = item.jumlah_belum_dibuka;
        document.getElementById('adj_peratus').value = item.peratus_baki;
        document.getElementById('modalEditLink').href = '/inventori/' + item.id + '/edit';

        const deleteForm = document.getElementById('modalDeleteForm');
        if (deleteForm) {
            deleteForm.action = '/inventori/' + item.id;
        }

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');

        window.setTimeout(() => document.getElementById('adj_belum_dibuka').focus(), 0);
    }
    
    function tutupModalPelarasan() {
        const modal = document.getElementById('modalPelarasan');

        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        modalTriggerElement?.focus();
        modalTriggerElement = null;
    }

    document.getElementById('modalPelarasan').addEventListener('click', (event) => {
        if (event.target.id === 'modalPelarasan') {
            tutupModalPelarasan();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.getElementById('modalPelarasan').style.display !== 'none') {
            tutupModalPelarasan();
        }
    });
</script>
@endsection
