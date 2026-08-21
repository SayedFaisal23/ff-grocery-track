@extends('layouts.app')

@section('title', 'Barangan Perlu Restok')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Senarai Perlu Restok</h1>
        <p>Senarai barangan yang telah habis atau di bawah had ambang restok</p>
    </div>
</div>

<!-- Bahagian 1: Habis Stok -->
<div class="card inventori-list-card restock-section restock-section-danger" style="margin-bottom: 2rem;">
    <div class="card-header-flex">
        <h2 style="color: var(--color-danger); font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Habis Stok (Baki: 0)</span>
        </h2>
        <span class="badge badge-danger">{{ $habisStok->count() }} item</span>
    </div>
    
    <div class="table-wrapper desktop-only-view">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama/Jenama</th>
                    <th>Kategori</th>
                    <th>Had Ambang</th>
                    <th style="text-align: right;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($habisStok as $item)
                <tr>
                    <td data-label="Nama/Jenama"><strong>{{ $item->nama_item }}</strong></td>
                    <td data-label="Kategori"><x-kategori-pill :kategori="$item->kategoriPreset" /></td>
                    <td data-label="Had Ambang">{{ $item->had_ambang }} unit</td>
                    <td data-label="Tindakan" style="text-align: right;">
                        <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-plus"></i> Tambah Stok
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                        Tiada barangan yang kehabisan stok sepenuhnya. Bagus!
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- View Mudah Alih / Mobile View -->
    <div class="mobile-only-view">
        @forelse($habisStok as $item)
        <div class="mobile-item-card restock-mobile-card restock-mobile-card-danger">
            <div class="mobile-card-header restock-card-header">
                <div class="item-name-group">
                    <span class="item-name">{{ $item->nama_item }}</span>
                    <x-kategori-pill :kategori="$item->kategoriPreset" />
                </div>
                <div class="item-status restock-card-status">
                    <span class="badge badge-danger">Habis Stok</span>
                </div>
            </div>
            <div class="mobile-card-stats restock-card-stats">
                <div class="stat-box">
                    <span class="stat-label">Baki</span>
                    <span class="stat-val"><strong style="color: var(--color-danger);">0</strong> unit</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Had Ambang</span>
                    <span class="stat-val"><strong>{{ $item->had_ambang }}</strong> unit</span>
                </div>
            </div>
            <div class="mobile-card-actions restock-card-actions">
                <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Tambah Stok
                </a>
            </div>
        </div>
        @empty
        <div class="mobile-empty-state">
            Tiada barangan yang kehabisan stok sepenuhnya. Bagus!
        </div>
        @endforelse
    </div>
</div>

<!-- Bahagian 2: Bawah Ambang -->
<div class="card inventori-list-card restock-section restock-section-warning">
    <div class="card-header-flex">
        <h2 style="color: var(--color-warning); font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Kuantiti Dibawah Had</span>
        </h2>
        <span class="badge badge-warning">{{ $bawahAmbang->count() }} item</span>
    </div>
    
    <div class="table-wrapper desktop-only-view">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama/Jenama</th>
                    <th>Kategori</th>
                    <th>Baki</th>
                    <th>Had Ambang</th>
                    <th style="text-align: right;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bawahAmbang as $item)
                <tr>
                    <td data-label="Nama/Jenama"><strong>{{ $item->nama_item }}</strong></td>
                    <td data-label="Kategori"><x-kategori-pill :kategori="$item->kategoriPreset" /></td>
                    <td data-label="Baki"><span style="color: var(--color-warning); font-weight: 600;">{{ $item->jumlah_belum_dibuka }}</span> unit</td>
                    <td data-label="Had Ambang">{{ $item->had_ambang }} unit</td>
                    <td data-label="Tindakan" style="text-align: right;">
                        <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-pen"></i> Kemaskini
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                        Tiada barangan di bawah had ambang restok.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- View Mudah Alih / Mobile View -->
    <div class="mobile-only-view">
        @forelse($bawahAmbang as $item)
        <div class="mobile-item-card restock-mobile-card restock-mobile-card-warning">
            <div class="mobile-card-header restock-card-header">
                <div class="item-name-group">
                    <span class="item-name">{{ $item->nama_item }}</span>
                    <x-kategori-pill :kategori="$item->kategoriPreset" />
                </div>
                <div class="item-status restock-card-status">
                    <span class="badge badge-warning">Bawah Had</span>
                </div>
            </div>
            <div class="mobile-card-stats restock-card-stats">
                <div class="stat-box">
                    <span class="stat-label">Baki</span>
                    <span class="stat-val"><strong style="color: var(--color-warning);">{{ $item->jumlah_belum_dibuka }}</strong> unit</span>
                </div>
                <div class="stat-box baki-box">
                    <span class="stat-label">Had Ambang</span>
                    <span class="stat-val"><strong>{{ $item->had_ambang }}</strong> unit</span>
                </div>
            </div>
            <div class="mobile-card-actions restock-card-actions">
                <a href="{{ route('inventori.edit', $item->id) }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-pen"></i> Kemaskini
                </a>
            </div>
        </div>
        @empty
        <div class="mobile-empty-state">
            Tiada barangan di bawah had ambang restok.
        </div>
        @endforelse
    </div>
</div>
@endsection
