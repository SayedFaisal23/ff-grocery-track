<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tuntutan extends Model
{
    protected $table = 'tuntutan';

    protected $fillable = [
        'user_id',
        'requestor_name',
        'nama_item',
        'tag',
        'nilai_tuntutan',
        'tarikh_beli',
        'minggu_tuntutan',
        'status',
        'attachment',
        'request_date',
        'item_specification',
        'purchase_purpose',
        'invoice_no',
        'purchase_platform',
        'total_item_amount',
        'payment_method',
        'invoice_sent_to_account',
        'date_receive',
        'approval_result',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'tarikh_beli' => 'date',
        'request_date' => 'date',
        'date_receive' => 'date',
        'nilai_tuntutan' => 'decimal:2',
        'total_item_amount' => 'decimal:2',
        'invoice_sent_to_account' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the user who submitted the claim.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the Superadmin who completed this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
