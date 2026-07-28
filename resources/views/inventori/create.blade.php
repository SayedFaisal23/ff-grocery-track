@extends('layouts.app')

@section('title', 'Tambah Barang')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Tambah Barangan Baharu</h1>
        <p>Masukkan maklumat barangan dapur ke dalam inventori</p>
    </div>
    <a href="{{ route('inventori.index') }}" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="card" style="max-width: 680px;">
    <form action="{{ route('inventori.store') }}" method="POST">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label for="nama_item" class="form-label">Nama/Jenama</label>
                <input type="text" name="nama_item" id="nama_item" class="form-control @error('nama_item') is-invalid @enderror" placeholder="Contoh: Susu Segar Farm Fresh" value="{{ old('nama_item') }}" required>
                @error('nama_item')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="kategori_id" class="form-label">Kategori</label>
                <select name="kategori_id" id="kategori_id" class="form-control @error('kategori_id') is-invalid @enderror" required>
                    <option value="">Pilih Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) old('kategori_id') === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->nama }}
                        </option>
                    @endforeach
                </select>
                @if($categories->isEmpty())
                    <small style="color: var(--color-warning); display: block; margin-top: 4px;">
                        Admin perlu menambah kategori terlebih dahulu.
                    </small>
                @endif
                @error('kategori_id')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-row" style="margin-top: 1rem; margin-bottom: 1rem;">
            <div class="form-group">
                <label for="jenis" class="form-label">Varian</label>
                <input type="text" name="jenis" id="jenis" class="form-control @error('jenis') is-invalid @enderror" placeholder="Contoh: Original, Strawberi" value="{{ old('jenis') }}">
                @error('jenis')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="capacity" class="form-label">Kapasiti (ml/g/kg)</label>
                <input type="text" name="capacity" id="capacity" class="form-control @error('capacity') is-invalid @enderror" placeholder="Contoh: 1L, 500g, 2kg" value="{{ old('capacity') }}">
                @error('capacity')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="jumlah_belum_dibuka" class="form-label">Baki (Unit)</label>
                <input type="number" name="jumlah_belum_dibuka" id="jumlah_belum_dibuka" class="form-control @error('jumlah_belum_dibuka') is-invalid @enderror" min="0" value="{{ old('jumlah_belum_dibuka', 0) }}" required>
                @error('jumlah_belum_dibuka')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="had_ambang" class="form-label">Had Ambang Restok (Kuantiti Minimum)</label>
                <input type="number" name="had_ambang" id="had_ambang" class="form-control @error('had_ambang') is-invalid @enderror" min="0" value="{{ old('had_ambang', 1) }}" required>
                <small style="color: var(--text-dark); display: block; margin-top: 4px;">Sistem akan memberi amaran apabila baki stok jatuh ke tahap ini.</small>
                @error('had_ambang')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <input type="hidden" name="peratus_baki" value="100">

        <div class="form-row" style="margin-top: 1rem;">
            <div class="form-group">
                <label for="tarikh_luput" class="form-label">Tarikh Luput</label>
                <input type="date" name="tarikh_luput" id="tarikh_luput" class="form-control @error('tarikh_luput') is-invalid @enderror" value="{{ old('tarikh_luput') }}">
                @error('tarikh_luput')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="display: flex; align-items: center; padding-top: 2rem;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="jejak_luput" value="1" {{ old('jejak_luput') ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--color-primary);">
                    <span style="font-weight: 500;">Jejak tarikh luput untuk barang ini</span>
                </label>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <a href="{{ route('inventori.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary" {{ $categories->isEmpty() ? 'disabled' : '' }}>Simpan Barang</button>
        </div>
    </form>
</div>
@endsection
