<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventori extends Model
{
    protected $table = 'inventori';

    protected $fillable = [
        'nama_item',
        'kategori_id',
        'jenis',
        'capacity',
        'jumlah_belum_dibuka',
        'peratus_baki',
        'tarikh_luput',
        'jejak_luput',
        'had_ambang',
        'dicipta_oleh',
        'dikemaskini_oleh',
    ];

    protected $casts = [
        'jejak_luput' => 'boolean',
        'tarikh_luput' => 'date',
        'jumlah_belum_dibuka' => 'integer',
        'peratus_baki' => 'integer',
        'had_ambang' => 'integer',
    ];

    protected $appends = [
        'kategori',
    ];

    public function kategoriPreset(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function getKategoriAttribute(): ?string
    {
        return $this->kategoriPreset?->nama;
    }

    /**
     * Get the user who created the item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicipta_oleh');
    }

    /**
     * Get the user who last updated the item.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikemaskini_oleh');
    }
}
