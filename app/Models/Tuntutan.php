<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tuntutan extends Model
{
    public const OTHER_PAYMENT_METHOD = 'Lain-lain';

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
        'other_payment_method',
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

    /**
     * Determine whether this claim follows the purchase-request workflow.
     */
    public function isPurchaseRequest(): bool
    {
        return in_array($this->tag, ['Pantry', 'General'], true);
    }

    /**
     * Determine whether a Superadmin can still make the first decision.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === 'Pending' && $this->approval_result === null;
    }

    /**
     * Determine whether the request owner can upload the required receipt.
     */
    public function canUploadAttachment(): bool
    {
        return $this->isPurchaseRequest()
            && $this->status === 'Pending'
            && $this->approval_result === 'Approved'
            && $this->attachment === null;
    }

    /**
     * Return one human-readable workflow state for display without changing
     * the existing persisted approval workflow.
     *
     * @return array{label: string, tone: string, message: string}
     */
    public function workflowStatus(): array
    {
        if ($this->canBeReviewed()) {
            return [
                'label' => 'Submitted',
                'tone' => 'warning',
                'message' => 'Awaiting Superadmin review.',
            ];
        }

        if ($this->canUploadAttachment()) {
            return [
                'label' => 'Approved - receipt required',
                'tone' => 'primary',
                'message' => 'The requester must upload the receipt to complete this claim.',
            ];
        }

        if ($this->approval_result === 'Rejected') {
            return [
                'label' => 'Rejected',
                'tone' => 'danger',
                'message' => 'No further action is required.',
            ];
        }

        return [
            'label' => 'Completed',
            'tone' => 'success',
            'message' => 'This claim has been completed.',
        ];
    }

    /**
     * Format the stored payment method for claim detail displays.
     */
    public function paymentMethodDisplay(): string
    {
        if ($this->payment_method === self::OTHER_PAYMENT_METHOD && $this->other_payment_method) {
            return self::OTHER_PAYMENT_METHOD.' — '.$this->other_payment_method;
        }

        return (string) $this->payment_method;
    }
}
