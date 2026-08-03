<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TuntutanPreset extends Model
{
    public const TYPE_PURCHASE_PLATFORM = 'purchase_platform';
    public const TYPE_PAYMENT_METHOD = 'payment_method';

    protected $table = 'tuntutan_presets';

    protected $fillable = [
        'type',
        'name',
        'sort_order',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_PURCHASE_PLATFORM,
            self::TYPE_PAYMENT_METHOD,
        ];
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
