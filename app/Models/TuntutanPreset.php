<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TuntutanPreset extends Model
{
    public const TYPE_PURCHASE_PLATFORM = 'purchase_platform';
    public const TYPE_PAYMENT_METHOD = 'payment_method';
    public const PAYMENT_WORKFLOW_DIRECTOR_CC = 'director_cc';
    public const PAYMENT_WORKFLOW_COMPANY_TRANSFER = 'company_transfer';

    protected $table = 'tuntutan_presets';

    protected $fillable = [
        'type',
        'name',
        'payment_workflow',
        'sort_order',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_PURCHASE_PLATFORM,
            self::TYPE_PAYMENT_METHOD,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function paymentWorkflows(): array
    {
        return [
            self::PAYMENT_WORKFLOW_DIRECTOR_CC,
            self::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
        ];
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
