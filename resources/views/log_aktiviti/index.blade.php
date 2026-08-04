@extends('layouts.app')

@section('title', 'Log Aktiviti')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>Log Aktiviti Sistem</h1>
        <p>Jejak dan audit semua tindakan penting oleh pengguna sistem</p>
    </div>
</div>

<div class="card" style="padding: 0;">
    <div class="table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 180px;">Tarikh & Masa</th>
                    <th style="width: 200px;">Pengguna</th>
                    <th>Aktiviti</th>
                    <th>Perubahan Data</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        <span class="log-timestamp">{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                    </td>
                    <td>
                        @if($log->user)
                            <strong>{{ $log->user->name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $log->user->roles->first()?->name ?? 'Tiada Peranan' }}</div>
                        @else
                            <span style="color: var(--text-dark); font-style: italic;">Sistem / Pengguna Dipadam</span>
                        @endif
                    </td>
                    <td>
                        <span class="log-activity-text">{{ $log->aktiviti }}</span>
                    </td>
                    <td>
                        @if($log->data_lama || $log->data_baru)
                            <details style="cursor: pointer; font-size: 0.85rem;">
                                <summary style="color: var(--color-primary); font-weight: 500; outline: none;">Lihat Butiran Perubahan</summary>
                                <div style="margin-top: 8px; background: rgba(0,0,0,0.3); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); overflow-x: auto; max-width: 450px;">
                                    @if($log->data_lama)
                                        <div style="margin-bottom: 8px;">
                                            <strong style="color: var(--color-danger); font-size: 0.75rem;">SEBELUM:</strong>
                                            <pre style="font-family: monospace; font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; white-space: pre-wrap;">{{ json_encode($log->data_lama, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                    @if($log->data_baru)
                                        <div>
                                            <strong style="color: var(--color-success); font-size: 0.75rem;">SELEPAS:</strong>
                                            <pre style="font-family: monospace; font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; white-space: pre-wrap;">{{ json_encode($log->data_baru, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @else
                            <span style="color: var(--text-dark); font-size: 0.85rem;">Tiada data perubahan</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        Tiada sebarang log aktiviti direkodkan lagi.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="padding: 1.5rem;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
