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
    $paymentWorkflowOptions = [
        'director_cc' => 'Director credit card',
        'company_transfer' => 'Company transfer',
    ];

    $presetGroups = [
        \App\Models\TuntutanPreset::TYPE_PURCHASE_PLATFORM => [
            'title' => 'Platform Pembelian',
            'description' => 'Contoh: Shopee, Lazada, kedai fizikal.',
            'items' => $platforms,
        ],
        \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD => [
            'title' => 'Saluran / Kaedah Bayaran',
            'description' => 'Tetapkan aliran kerja setiap kaedah bayaran. Pilihan tanpa aliran kerja tidak boleh digunakan dalam borang baharu.',
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

            <form action="{{ route('tuntutan-preset.store') }}" method="POST" class="preset-entry-form {{ $type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD ? 'preset-entry-form-payment' : '' }}">
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
                @if($type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD)
                    <div class="preset-workflow-field">
                        <label for="new-{{ $type }}-workflow" class="sr-only">Aliran kerja bayaran</label>
                        <select
                            id="new-{{ $type }}-workflow"
                            name="payment_workflow"
                            class="form-control @error('payment_workflow') is-invalid @enderror"
                            required
                        >
                            <option value="">Pilih aliran kerja</option>
                            @foreach($paymentWorkflowOptions as $workflow => $label)
                                <option value="{{ $workflow }}" @selected(old('type') === $type && old('payment_workflow') === $workflow)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Tambah</button>
            </form>

            @if(old('type') === $type)
                @error('name')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin: -0.75rem 0 1rem;">{{ $message }}</div>
                @enderror
                @error('payment_workflow')
                    <div style="color: var(--color-danger); font-size: 0.8rem; margin: -0.75rem 0 1rem;">{{ $message }}</div>
                @enderror
            @endif

            @if($type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD)
                <p style="color: var(--text-dark); font-size: 0.78rem; margin: -0.35rem 0 1rem;">
                    <strong>Lain-lain</strong> sentiasa menggunakan aliran <strong>Own expenses</strong> dan tidak perlu ditambah di sini.
                </p>
            @endif

            <div class="table-wrapper preset-table-wrapper">
                <table class="custom-table preset-table {{ $type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD ? 'preset-table-payment' : '' }}" data-preset-reorder-url="{{ route('tuntutan-preset.reorder') }}">
                    <thead>
                        <tr>
                            <th>Pilihan</th>
                            @if($type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD)
                                <th>Aliran kerja</th>
                            @endif
                            <th class="preset-reorder-header">Susun</th>
                            <th style="text-align: right;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody data-preset-list data-preset-type="{{ $type }}">
                        @forelse($group['items'] as $preset)
                            <tr data-preset-row data-preset-id="{{ $preset->id }}">
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
                                @if($type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD)
                                    <td>
                                        <label for="payment-workflow-{{ $preset->id }}" class="sr-only">Aliran kerja untuk {{ $preset->name }}</label>
                                        <select
                                            id="payment-workflow-{{ $preset->id }}"
                                            name="payment_workflow"
                                            form="preset-update-{{ $preset->id }}"
                                            class="form-control"
                                            required
                                        >
                                            <option value="">Belum ditetapkan</option>
                                            @foreach($paymentWorkflowOptions as $workflow => $label)
                                                <option value="{{ $workflow }}" @selected($preset->payment_workflow === $workflow)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif
                                <td class="preset-reorder-cell">
                                    <button
                                        type="button"
                                        class="preset-drag-handle"
                                        draggable="true"
                                        data-preset-drag-handle
                                        aria-label="Seret {{ $preset->name }} untuk menyusun semula"
                                        aria-pressed="false"
                                    >
                                        @for($dot = 0; $dot < 6; $dot++)
                                            <span aria-hidden="true"></span>
                                        @endfor
                                        <span class="sr-only">Seret untuk menyusun semula</span>
                                    </button>
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
                                <td colspan="{{ $type === \App\Models\TuntutanPreset::TYPE_PAYMENT_METHOD ? 4 : 3 }}" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">
                                    Belum ada pilihan. Tambah pilihan sebelum Stocker menghantar permohonan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="preset-reorder-status" data-preset-reorder-status role="status" aria-live="polite"></p>
        </section>
    @endforeach
</div>

<script>
    (() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        document.querySelectorAll('[data-preset-list]').forEach((list) => {
            const table = list.closest('[data-preset-reorder-url]');
            const status = table?.closest('.preset-group-card')?.querySelector('[data-preset-reorder-status]');
            const reorderUrl = table?.dataset.presetReorderUrl;
            let activeRow = null;
            let activeHandle = null;
            let initialOrder = [];
            let dropped = false;
            let isSaving = false;

            if (!table || !status || !reorderUrl || !csrfToken) {
                return;
            }

            const rows = () => Array.from(list.querySelectorAll('[data-preset-row]'));
            const currentOrder = () => rows().map((row) => row.dataset.presetId);
            const sameOrder = (first, second) => first.length === second.length && first.every((id, index) => id === second[index]);

            const restoreOrder = (order) => {
                order.forEach((presetId) => {
                    const row = list.querySelector(`[data-preset-id="${presetId}"]`);
                    if (row) {
                        list.append(row);
                    }
                });
            };

            const moveRowToPointer = (row, clientY) => {
                const target = rows().find((candidate) => {
                    if (candidate === row) {
                        return false;
                    }

                    const bounds = candidate.getBoundingClientRect();
                    return clientY < bounds.top + (bounds.height / 2);
                });

                if (target) {
                    list.insertBefore(row, target);
                } else {
                    list.append(row);
                }
            };

            const beginReorder = (row, handle) => {
                if (isSaving || activeRow) {
                    return false;
                }

                activeRow = row;
                activeHandle = handle;
                initialOrder = currentOrder();
                dropped = false;
                row.classList.add('is-reordering');
                handle.setAttribute('aria-pressed', 'true');
                status.textContent = 'Menyusun semula pilihan. Lepaskan untuk simpan.';

                return true;
            };

            const endReorder = () => {
                activeRow?.classList.remove('is-reordering');
                activeHandle?.setAttribute('aria-pressed', 'false');
                activeRow = null;
                activeHandle = null;
            };

            const saveOrder = async (previousOrder) => {
                const presetIds = currentOrder();

                if (sameOrder(previousOrder, presetIds)) {
                    status.textContent = '';
                    return;
                }

                isSaving = true;
                list.classList.add('is-saving');
                status.textContent = 'Menyimpan susunan...';

                try {
                    const response = await fetch(reorderUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            type: list.dataset.presetType,
                            preset_ids: presetIds,
                        }),
                    });

                    if (!response.ok) {
                        throw new Error('Unable to save preset order.');
                    }

                    status.textContent = 'Susunan pilihan berjaya disimpan.';
                } catch (error) {
                    restoreOrder(previousOrder);
                    status.textContent = 'Susunan tidak dapat disimpan. Susunan asal telah dipulihkan.';
                } finally {
                    isSaving = false;
                    list.classList.remove('is-saving');
                }
            };

            list.querySelectorAll('[data-preset-drag-handle]').forEach((handle) => {
                const row = handle.closest('[data-preset-row]');

                if (!row) {
                    return;
                }

                handle.addEventListener('dragstart', (event) => {
                    if (!beginReorder(row, handle)) {
                        event.preventDefault();
                        return;
                    }

                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', row.dataset.presetId);
                });

                handle.addEventListener('dragend', () => {
                    if (!activeRow) {
                        return;
                    }

                    if (!dropped) {
                        restoreOrder(initialOrder);
                        status.textContent = '';
                    }

                    endReorder();
                });

                handle.addEventListener('pointerdown', (event) => {
                    if (event.pointerType === 'mouse' || !beginReorder(row, handle)) {
                        return;
                    }

                    event.preventDefault();
                    handle.setPointerCapture(event.pointerId);
                });

                handle.addEventListener('pointermove', (event) => {
                    if (event.pointerType !== 'mouse' && activeRow === row) {
                        moveRowToPointer(row, event.clientY);
                    }
                });

                handle.addEventListener('pointerup', (event) => {
                    if (event.pointerType !== 'mouse' && activeRow === row) {
                        const previousOrder = initialOrder;
                        endReorder();
                        saveOrder(previousOrder);
                    }
                });

                handle.addEventListener('pointercancel', (event) => {
                    if (event.pointerType !== 'mouse' && activeRow === row) {
                        restoreOrder(initialOrder);
                        status.textContent = '';
                        endReorder();
                    }
                });

                handle.addEventListener('keydown', (event) => {
                    const isToggleKey = event.key === ' ' || event.key === 'Enter';

                    if (isToggleKey && !activeRow) {
                        event.preventDefault();
                        beginReorder(row, handle);
                        status.textContent = 'Gunakan anak panah atas atau bawah, kemudian tekan Enter untuk simpan.';
                        return;
                    }

                    if (activeRow !== row) {
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        restoreOrder(initialOrder);
                        status.textContent = '';
                        endReorder();
                        return;
                    }

                    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        const orderedRows = rows();
                        const currentIndex = orderedRows.indexOf(row);
                        const targetIndex = event.key === 'ArrowUp' ? currentIndex - 1 : currentIndex + 1;
                        const target = orderedRows[targetIndex];

                        if (!target) {
                            return;
                        }

                        if (event.key === 'ArrowUp') {
                            list.insertBefore(row, target);
                        } else {
                            list.insertBefore(row, target.nextSibling);
                        }

                        return;
                    }

                    if (isToggleKey) {
                        event.preventDefault();
                        const previousOrder = initialOrder;
                        endReorder();
                        saveOrder(previousOrder);
                    }
                });
            });

            list.addEventListener('dragover', (event) => {
                if (!activeRow) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                moveRowToPointer(activeRow, event.clientY);
            });

            list.addEventListener('drop', (event) => {
                if (!activeRow) {
                    return;
                }

                event.preventDefault();
                dropped = true;
                const previousOrder = initialOrder;
                endReorder();
                saveOrder(previousOrder);
            });
        });
    })();
</script>
@endsection
