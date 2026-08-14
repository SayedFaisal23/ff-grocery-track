<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TuntutanPaymentProofView extends Model
{
    public $timestamps = false;

    protected $table = 'tuntutan_payment_proof_views';

    protected $fillable = [
        'tuntutan_id',
        'user_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    /**
     * Get the claim whose payment proof was reviewed.
     */
    public function tuntutan(): BelongsTo
    {
        return $this->belongsTo(Tuntutan::class, 'tuntutan_id');
    }

    /**
     * Get the user who reviewed the payment proof.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
