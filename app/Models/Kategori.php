<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    public const DEFAULT_WARNA = '#6366F1';

    protected $table = 'categories';

    protected $fillable = [
        'nama',
        'warna',
    ];

    public static function normalizeWarna(mixed $warna): ?string
    {
        if (! is_string($warna)) {
            return null;
        }

        $warna = strtoupper(trim($warna));

        return preg_match('/^#[0-9A-F]{6}$/', $warna) ? $warna : null;
    }

    public function getWarnaAttribute(?string $value): string
    {
        return self::normalizeWarna($value) ?? self::DEFAULT_WARNA;
    }

    public static function pillBackgroundColorForWarna(string $warna): string
    {
        return (self::normalizeWarna($warna) ?? self::DEFAULT_WARNA).'26';
    }

    public function getPillBackgroundColorAttribute(): string
    {
        return self::pillBackgroundColorForWarna($this->warna);
    }

    public function inventori(): HasMany
    {
        return $this->hasMany(Inventori::class, 'kategori_id');
    }
}
