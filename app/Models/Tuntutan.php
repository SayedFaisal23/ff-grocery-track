<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tuntutan extends Model
{
    public const OTHER_PAYMENT_METHOD = 'Lain-lain';
    public const OTHER_PAYMENT_METHOD_DETAIL = 'Own expenses';

    /** @var array<int, string> */
    public const FILTERABLE_TYPES = ['Pantry', 'General', 'Lunch'];

    /** @var array<int, string> */
    public const FILTERABLE_WORKFLOW_STATUSES = [
        'submitted',
        'receipt_required',
        'completed',
        'rejected',
    ];

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
        'receipt_viewed_by',
        'receipt_viewed_at',
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
        'receipt_viewed_at' => 'datetime',
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
     * Get the Superadmin who first viewed an uploaded purchase receipt.
     */
    public function receiptViewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receipt_viewed_by');
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
     * Limit the query to claims that still require a Superadmin decision.
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query
            ->where('status', 'Pending')
            ->whereNull('approval_result');
    }

    /**
     * Limit the query to approved purchase requests awaiting the requester's
     * receipt upload.
     */
    public function scopeAwaitingReceiptUpload(Builder $query): Builder
    {
        return $query
            ->whereIn('tag', ['Pantry', 'General'])
            ->where('status', 'Pending')
            ->where('approval_result', 'Approved')
            ->whereNull('attachment');
    }

    /**
     * Limit the query to new purchase receipts that a Superadmin has not yet
     * opened. Lunch supporting documents are deliberately excluded.
     */
    public function scopeAwaitingReceiptReview(Builder $query): Builder
    {
        return $query
            ->whereIn('tag', ['Pantry', 'General'])
            ->where('status', 'Completed')
            ->where('approval_result', 'Approved')
            ->whereNotNull('attachment')
            ->whereNull('receipt_viewed_at');
    }

    /**
     * Determine whether this completed purchase receipt still needs a
     * Superadmin to open it.
     */
    public function isAwaitingReceiptReview(): bool
    {
        return $this->isPurchaseRequest()
            && $this->status === 'Completed'
            && $this->approval_result === 'Approved'
            && $this->attachment !== null
            && $this->receipt_viewed_at === null;
    }

    /**
     * Apply one of the visible claim workflow stages to a query.
     */
    public function scopeWithWorkflowStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'submitted' => $query->awaitingReview(),
            'receipt_required' => $query->awaitingReceiptUpload(),
            'rejected' => $query->where('approval_result', 'Rejected'),
            'completed' => $query->whereNot(function (Builder $statusQuery): void {
                $statusQuery
                    ->where(function (Builder $reviewQuery): void {
                        $reviewQuery->awaitingReview();
                    })
                    ->orWhere(function (Builder $receiptQuery): void {
                        $receiptQuery->awaitingReceiptUpload();
                    })
                    ->orWhere('approval_result', 'Rejected');
            }),
            default => $query,
        };
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
