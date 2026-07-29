@extends('layouts.app')

@section('title', 'Pengurusan Kategori')

@section('content')
@php
    $newCategoryColor = \App\Models\Kategori::normalizeWarna(old('warna'))
        ?? \App\Models\Kategori::DEFAULT_WARNA;
    $newCategoryNameValue = old('nama');
    $newCategoryNameValue = is_string($newCategoryNameValue) ? $newCategoryNameValue : '';
    $newCategoryName = $newCategoryNameValue !== '' ? $newCategoryNameValue : 'Pratonton Kategori';
@endphp

<div class="page-header">
    <div class="page-title">
        <h1>Pengurusan Kategori</h1>
        <p>Tetapkan kategori yang boleh dipilih untuk item inventori</p>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.1rem; margin-bottom: 1rem;">Tambah Kategori</h2>
    <form action="{{ route('kategori.store') }}" method="POST" class="kategori-create-form">
        @csrf
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label for="nama" class="form-label">Nama Kategori</label>
            <input
                type="text"
                name="nama"
                id="nama"
                class="form-control @error('nama') is-invalid @enderror"
                value="{{ $newCategoryNameValue }}"
                placeholder="Contoh: Tenusu, Minuman, Rencah"
                data-category-name-input
                data-preview="newCategoryPreview"
                required
            >
            @error('nama')
                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group kategori-warna-form-group">
            <label for="warna" class="form-label">Warna Pill</label>
            <div class="kategori-color-control">
                <input
                    type="color"
                    name="warna"
                    id="warna"
                    class="kategori-color-picker @error('warna') is-invalid @enderror"
                    value="{{ $newCategoryColor }}"
                    data-category-color-input
                    data-preview="newCategoryPreview"
                    required
                >
                <span
                    id="newCategoryPreview"
                    class="badge kategori-pill"
                    style="background-color: {{ \App\Models\Kategori::pillBackgroundColorForWarna($newCategoryColor) }}; color: {{ $newCategoryColor }};"
                >{{ $newCategoryName }}</span>
            </div>
            @error('warna')
                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary kategori-create-button">
            <i class="fa-solid fa-plus"></i> Tambah
        </button>
    </form>
</div>

<div class="card inventori-list-card" style="padding: 0;">
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
                    <th>Warna</th>
                    <th>Jumlah Item</th>
                    <th style="text-align: right;">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    @php
                        $oldCategoryName = old("categories.{$category->id}.nama");
                        $categoryName = is_string($oldCategoryName) ? $oldCategoryName : $category->nama;
                        $categoryColor = \App\Models\Kategori::normalizeWarna(old("categories.{$category->id}.warna"))
                            ?? $category->warna;
                    @endphp
                    <tr>
                        <td>
                            <input
                                type="text"
                                name="categories[{{ $category->id }}][nama]"
                                form="kategoriBulkForm"
                                class="form-control @error("categories.{$category->id}.nama") is-invalid @enderror"
                                value="{{ $categoryName }}"
                                aria-label="Nama kategori {{ $category->nama }}"
                                data-category-name-input
                                data-preview="categoryPreview-{{ $category->id }}"
                                required
                            >
                            @error("categories.{$category->id}.nama")
                                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <div class="kategori-color-control">
                                <input
                                    type="color"
                                    name="categories[{{ $category->id }}][warna]"
                                    form="kategoriBulkForm"
                                    class="kategori-color-picker @error("categories.{$category->id}.warna") is-invalid @enderror"
                                    value="{{ $categoryColor }}"
                                    aria-label="Warna kategori {{ $category->nama }}"
                                    data-category-color-input
                                    data-preview="categoryPreview-{{ $category->id }}"
                                    required
                                >
                                <span
                                    id="categoryPreview-{{ $category->id }}"
                                    class="badge kategori-pill"
                                    style="background-color: {{ \App\Models\Kategori::pillBackgroundColorForWarna($categoryColor) }}; color: {{ $categoryColor }};"
                                >{{ $categoryName }}</span>
                            </div>
                            @error("categories.{$category->id}.warna")
                                <div style="color: var(--color-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <span class="badge badge-primary">{{ $category->inventori_count }} item</span>
                        </td>
                        <td style="text-align: right;">
                            @if($category->inventori_count > 0)
                                <span
                                    title="Kategori ini masih digunakan oleh item inventori."
                                    style="color: var(--text-muted); font-size: 0.8rem;"
                                >
                                    <i class="fa-solid fa-circle-info"></i>
                                    Tidak boleh dipadam: digunakan oleh item inventori
                                </span>
                            @else
                                <form
                                    action="{{ route('kategori.destroy', $category) }}"
                                    method="POST"
                                    onsubmit="return confirm('Adakah anda pasti mahu memadam kategori ini?')"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" title="Padam kategori">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            Belum ada kategori. Tambah kategori pertama untuk digunakan dalam inventori.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    (() => {
        const defaultColor = '{{ \App\Models\Kategori::DEFAULT_WARNA }}';

        const normaliseColor = (color) => /^#[0-9a-f]{6}$/i.test(color)
            ? color.toUpperCase()
            : defaultColor;

        document.querySelectorAll('[data-category-color-input]').forEach((colorInput) => {
            const preview = document.getElementById(colorInput.dataset.preview);
            const nameInput = document.querySelector(
                `[data-category-name-input][data-preview="${colorInput.dataset.preview}"]`
            );

            if (! preview || ! nameInput) {
                return;
            }

            const updatePreview = () => {
                const color = normaliseColor(colorInput.value);
                colorInput.value = color;
                preview.style.backgroundColor = `${color}26`;
                preview.style.color = color;
                preview.textContent = nameInput.value.trim() || 'Pratonton Kategori';
            };

            colorInput.addEventListener('input', updatePreview);
            colorInput.addEventListener('change', updatePreview);
            nameInput.addEventListener('input', updatePreview);
            updatePreview();
        });
    })();
</script>
@endsection
