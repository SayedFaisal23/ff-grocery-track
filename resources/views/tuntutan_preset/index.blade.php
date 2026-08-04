@extends('layouts.app')

@section('title', 'Tetapan Tuntutan')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Tetapan Tuntutan</h1>
        <p>Urus pilihan tetap untuk borang permohonan pembelian</p>
    </div>
</div>

@php
    $presetGroups = [
        \App\Models\TuntutanPreset::TYPE_PURCHASE_PLATFORM => [
            'title' => 'Platform Pembelian',
            'description' => 'Contoh: Shopee, Lazada, kedai fizikal.',
            'items' => $platforms,
        ],
        \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD => [
            'title' => 'Saluran / Kaedah Bayaran',
            'description' => 'Contoh: Bank transfer, kad kredit, tunai.',
            'items' => $paymentMethods,
        ],
    ];
@endphp

<div class="preset-groups-grid">
    @foreach($presetGroups as $type => $group)
        <section class="card preset-group-card">
            <div style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.1rem; margin-bottom: 0.3rem;">{{ $group['title'] }}</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">{{ $group['description'] }}</p>
            </div>

            <form action="{{ route('tuntutan-preset.store') }}" method="POST" class="preset-entry-form">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div style="flex: 1;">
                    <label for="new-{{ $type }}" class="sr-only">Pilihan baharu</label>
                    <input
                        type="text"
                        id="new-{{ $type }}"
                        name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('type') === $type ? old('name') : '' }}"
                        placeholder="Tambah pilihan"
                        maxlength="255"
                        required
                    >
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah</button>
            </form>

            @if(old('type') === $type)
                @error('name')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin: -0.75rem 0 1rem;">{{ $message }}</div>
                @enderror
            @endif

            <div class="table-wrapper preset-table-wrapper">
                <table class="custom-table preset-table">
                    <thead>
                        <tr>
                            <th>Pilihan</th>
                            <th style="width: 95px;">Turutan</th>
                            <th style="text-align: right;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group['items'] as $preset)
                            <tr>
                                <td>
                                    <input
                                        type="text"
                                        name="name"
                                        form="preset-update-{{ $preset->id }}"
                                        class="form-control"
                                        value="{{ $preset->name }}"
                                        maxlength="255"
                                        aria-label="Nama pilihan {{ $preset->name }}"
                                        required
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="sort_order"
                                        form="preset-update-{{ $preset->id }}"
                                        class="form-control"
                                        value="{{ $preset->sort_order }}"
                                        min="0"
                                        aria-label="Turutan {{ $preset->name }}"
                                        required
                                    >
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <form id="preset-update-{{ $preset->id }}" action="{{ route('tuntutan-preset.update', $preset) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="type" value="{{ $type }}">
                                    </form>
                                    <button type="submit" form="preset-update-{{ $preset->id }}" class="btn btn-secondary btn-sm" title="Simpan pilihan">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                    <form action="{{ route('tuntutan-preset.destroy', $preset) }}" method="POST" style="display: inline;" onsubmit="return confirm('Padam pilihan ini? Rekod permohonan lama tidak akan berubah.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Padam pilihan">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                    Belum ada pilihan. Tambah pilihan sebelum Stocker menghantar permohonan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
@endsection
