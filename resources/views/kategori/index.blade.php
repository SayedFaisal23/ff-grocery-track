@extends('layouts.app')

@section('title', 'Pengurusan Kategori')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Pengurusan Kategori</h1>
        <p>Tetapkan kategori yang boleh dipilih untuk item inventori</p>
    </div>
</div>

<div class="card" style="max-width: 760px; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.1rem; margin-bottom: 1rem;">Tambah Kategori</h2>
    <form action="{{ route('kategori.store') }}" method="POST" style="display: flex; gap: 10px; align-items: flex-start;">
        @csrf
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label for="nama" class="form-label">Nama Kategori</label>
            <input
                type="text"
                name="nama"
                id="nama"
                class="form-control @error('nama') is-invalid @enderror"
                value="{{ old('nama') }}"
                placeholder="Contoh: Tenusu, Minuman, Rencah"
                required
            >
            @error('nama')
                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 27px;">
            <i class="fa-solid fa-plus"></i> Tambah
        </button>
    </form>
</div>

<div class="card inventori-list-card" style="max-width: 760px; padding: 0;">
    <form id="kategoriBulkForm" action="{{ route('kategori.update-all') }}" method="POST">
        @csrf
        @method('PUT')
    </form>
    <div class="card-header-flex" style="padding: 1.25rem 1.5rem; margin-bottom: 0;">
        <h2 style="font-size: 1.1rem;">Senarai Kategori</h2>
        <button type="submit" form="kategoriBulkForm" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Simpan
        </button>
    </div>
    @error('categories')
        <div style="color: var(--color-danger); font-size: 0.85rem; padding: 0 1.5rem 1rem;">{{ $message }}</div>
    @enderror
    <div class="table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th>Jumlah Item</th>
                    <th style="text-align: right;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <input
                                type="text"
                                name="categories[{{ $category->id }}]"
                                form="kategoriBulkForm"
                                class="form-control"
                                value="{{ old("categories.{$category->id}", $category->nama) }}"
                                aria-label="Nama kategori {{ $category->nama }}"
                                required
                            >
                        </td>
                        <td>
                            <span class="badge badge-primary">{{ $category->inventori_count }} item</span>
                        </td>
                        <td style="text-align: right;">
                            <form
                                action="{{ route('kategori.destroy', $category) }}"
                                method="POST"
                                onsubmit="return confirm('Adakah anda pasti mahu memadam kategori ini?')"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm"
                                    title="Padam kategori"
                                    {{ $category->inventori_count > 0 ? 'disabled' : '' }}
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            Belum ada kategori. Tambah kategori pertama untuk digunakan dalam inventori.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
