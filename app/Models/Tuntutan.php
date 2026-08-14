<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tuntutan extends Model
{
    public const OTHER_PAYMENT_METHOD = 'Lain-lain';
    public const OTHER_PAYMENT_METHOD_DETAIL = 'Own expenses';
    public const PAYMENT_WORKFLOW_LEGACY = 'legacy';
    public const PAYMENT_WORKFLOW_DIRECTOR_CC = 'director_cc';
    public const PAYMENT_WORKFLOW_COMPANY_TRANSFER = 'company_transfer';
    public const PAYMENT_WORKFLOW_OWN_EXPENSES = 'own_expenses';
    public const DOCUMENT_PURCHASE_ATTACHMENT = 'purchase_attachment';
    public const DOCUMENT_ATTACHMENT = 'attachment';
    public const DOCUMENT_PAYMENT_PROOF_ATTACHMENT = 'payment_proof_attachment';
    public const PRIVATE_DOCUMENT_PREFIX = 'claim-documents/';

    /** @var array<int, string> */
    public const FILTERABLE_TYPES = ['Pantry', 'General', 'Lunch'];

    /** @var array<int, string> */
    public const FILTERABLE_WORKFLOW_STATUSES = [
        'submitted',
        'requester_document_required',
        'payment_proof_required',
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
        'purchase_attachment',
        'payment_proof_attachment',
        'receipt_viewed_by',
        'receipt_viewed_at',
        'purchase_attachment_viewed_by',
        'purchase_attachment_viewed_at',
        'payment_proof_attachment_viewed_by',
        'payment_proof_attachment_viewed_at',
        'attachment_viewed_by',
        'attachment_viewed_at',
        'latest_attachment_downloaded_by',
        'latest_attachment_downloaded_at',
        'claim_details_viewed_by',
        'claim_details_viewed_at',
        'request_date',
        'item_specification',
        'purchase_purpose',
        'invoice_no',
        'purchase_platform',
        'total_item_amount',
        'payment_method',
        'other_payment_method',
        'payment_workflow',
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
        'purchase_attachment_viewed_at' => 'datetime',
        'payment_proof_attachment_viewed_at' => 'datetime',
        'attachment_viewed_at' => 'datetime',
        'latest_attachment_downloaded_at' => 'datetime',
        'claim_details_viewed_at' => 'datetime',
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
     * Get the Superadmin who first opened the purchase quotation/invoice.
     */
    public function purchaseAttachmentViewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchase_attachment_viewed_by');
    }

    /**
     * Get the Superadmin who first opened this claim's attachment/receipt.
     */
    public function attachmentViewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attachment_viewed_by');
    }

    /**
     * Get the Superadmin who first opened a company payment proof.
     */
    public function paymentProofAttachmentViewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_proof_attachment_viewed_by');
    }

    /**
     * Get the per-user reviews of this claim's company payment proof.
     */
    public function paymentProofViews(): HasMany
    {
        return $this->hasMany(TuntutanPaymentProofView::class, 'tuntutan_id');
    }

    /**
     * Get the Superadmin who most recently opened any claim document.
     */
    public function latestAttachmentDownloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'latest_attachment_downloaded_by');
    }

    /**
     * Get the Superadmin who most recently reviewed this claim's details.
     */
    public function claimDetailsViewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_details_viewed_by');
    }

    /**
     * Determine whether a supported document has a stored path.
     */
    public function hasDocument(string $document): bool
    {
        return $this->isDocumentColumn($document)
            && is_string($this->getAttribute($document))
            && $this->getAttribute($document) !== '';
    }

    /**
     * Determine whether a document is a new private upload that no
     * Superadmin has opened yet. Historic public attachments never pulse.
     */
    public function isDocumentAwaitingView(string $document): bool
    {
        if (! $this->hasDocument($document)) {
            return false;
        }

        $path = (string) $this->getAttribute($document);

        return str_starts_with($path, self::PRIVATE_DOCUMENT_PREFIX)
            && $this->documentViewedAt($document) === null;
    }

    /**
     * Return the first-view timestamp for a supported document.
     */
    public function documentViewedAt(string $document): mixed
    {
        return $this->getAttribute($this->documentViewedAtColumn($document));
    }

    /**
     * Return the timestamp column associated with a supported document.
     */
    public function documentViewedAtColumn(string $document): string
    {
        return $this->documentColumnMap($document)['viewed_at'];
    }

    /**
     * Return the viewer column associated with a supported document.
     */
    public function documentViewedByColumn(string $document): string
    {
        return $this->documentColumnMap($document)['viewed_by'];
    }

    private function isDocumentColumn(string $document): bool
    {
        return in_array($document, [
            self::DOCUMENT_PURCHASE_ATTACHMENT,
            self::DOCUMENT_ATTACHMENT,
            self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT,
        ], true);
    }

    /**
     * @return array{viewed_by: string, viewed_at: string}
     */
    private function documentColumnMap(string $document): array
    {
        return match ($document) {
            self::DOCUMENT_PURCHASE_ATTACHMENT => [
                'viewed_by' => 'purchase_attachment_viewed_by',
                'viewed_at' => 'purchase_attachment_viewed_at',
            ],
            self::DOCUMENT_ATTACHMENT => [
                'viewed_by' => 'attachment_viewed_by',
                'viewed_at' => 'attachment_viewed_at',
            ],
            self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT => [
                'viewed_by' => 'payment_proof_attachment_viewed_by',
                'viewed_at' => 'payment_proof_attachment_viewed_at',
            ],
            default => throw new \InvalidArgumentException('Unsupported claim document.'),
        };
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
     * Return the immutable workflow snapshot with its currently actionable
     * stage. Status remains backwards compatible with historic records.
     *
     * @return array{type: string, stage: string, next_actor: string|null, required_document: string|null}
     */
    public function workflow(): array
    {
        $type = $this->paymentWorkflow();

        if ($this->approval_result === 'Rejected') {
            return [
                'type' => $type,
                'stage' => 'rejected',
                'next_actor' => null,
                'required_document' => null,
            ];
        }

        if ($this->canBeReviewed()) {
            return [
                'type' => $type,
                'stage' => 'awaiting_approval',
                'next_actor' => 'superadmin',
                'required_document' => null,
            ];
        }

        if ($this->canUploadAttachment()) {
            return [
                'type' => $type,
                'stage' => 'awaiting_requester_document',
                'next_actor' => 'requester',
                'required_document' => self::DOCUMENT_ATTACHMENT,
            ];
        }

        if ($this->canUploadPaymentProof()) {
            return [
                'type' => $type,
                'stage' => 'awaiting_payment_proof',
                'next_actor' => 'superadmin',
                'required_document' => self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT,
            ];
        }

        return [
            'type' => $type,
            'stage' => 'completed',
            'next_actor' => null,
            'required_document' => null,
        ];
    }

    /**
     * Return the stored workflow or a legacy default for historic records.
     */
    public function paymentWorkflow(): string
    {
        return in_array($this->payment_workflow, [
            self::PAYMENT_WORKFLOW_DIRECTOR_CC,
            self::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            self::PAYMENT_WORKFLOW_OWN_EXPENSES,
            self::PAYMENT_WORKFLOW_LEGACY,
        ], true)
            ? $this->payment_workflow
            : self::PAYMENT_WORKFLOW_LEGACY;
    }

    public function isDirectorCreditCardPayment(): bool
    {
        return $this->paymentWorkflow() === self::PAYMENT_WORKFLOW_DIRECTOR_CC;
    }

    public function isCompanyTransferPayment(): bool
    {
        return $this->paymentWorkflow() === self::PAYMENT_WORKFLOW_COMPANY_TRANSFER;
    }

    public function isOwnExpensesPayment(): bool
    {
        return $this->paymentWorkflow() === self::PAYMENT_WORKFLOW_OWN_EXPENSES;
    }

    /**
     * Determine whether submission must include an invoice or quotation.
     */
    public function requiresPreApprovalDocument(): bool
    {
        return $this->isCompanyTransferPayment()
            || ($this->isDirectorCreditCardPayment() && $this->invoice_sent_to_account);
    }

    /**
     * Determine whether the request owner can upload the final document.
     */
    public function canUploadAttachment(): bool
    {
        if (! $this->isPurchaseRequest()
            || $this->status !== 'Pending'
            || $this->approval_result !== 'Approved'
            || $this->attachment !== null) {
            return false;
        }

        return in_array($this->paymentWorkflow(), [
            self::PAYMENT_WORKFLOW_LEGACY,
            self::PAYMENT_WORKFLOW_DIRECTOR_CC,
            self::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ], true)
            && ! ($this->isDirectorCreditCardPayment()
                && $this->invoice_sent_to_account
                && $this->purchase_attachment !== null);
    }

    /**
     * Determine whether a Superadmin must upload company payment evidence.
     */
    public function canUploadPaymentProof(): bool
    {
        return $this->isPurchaseRequest()
            && $this->isCompanyTransferPayment()
            && $this->status === 'Pending'
            && $this->approval_result === 'Approved'
            && $this->purchase_attachment !== null
            && $this->payment_proof_attachment === null;
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
     * Limit the query to approved purchase requests requiring the requester
     * to upload a receipt or invoice. The legacy branch preserves existing
     * pre-migration records.
     */
    public function scopeAwaitingRequesterDocumentUpload(Builder $query): Builder
    {
        return $query
            ->whereIn('tag', ['Pantry', 'General'])
            ->where('status', 'Pending')
            ->where('approval_result', 'Approved')
            ->whereNull('attachment')
            ->where(function (Builder $workflowQuery): void {
                $workflowQuery
                    ->whereNull('payment_workflow')
                    ->orWhere('payment_workflow', self::PAYMENT_WORKFLOW_LEGACY)
                    ->orWhere('payment_workflow', self::PAYMENT_WORKFLOW_OWN_EXPENSES)
                    ->orWhere(function (Builder $directorQuery): void {
                        $directorQuery
                            ->where('payment_workflow', self::PAYMENT_WORKFLOW_DIRECTOR_CC)
                            ->where(function (Builder $invoiceQuery): void {
                                $invoiceQuery
                                    ->whereNull('invoice_sent_to_account')
                                    ->orWhere('invoice_sent_to_account', false);
                            });
                    });
            });
    }

    /**
     * Backwards-compatible alias for existing consumers and filters.
     */
    public function scopeAwaitingReceiptUpload(Builder $query): Builder
    {
        return $query->awaitingRequesterDocumentUpload();
    }

    /**
     * Limit the query to approved company-transfer requests waiting for the
     * Superadmin's proof of payment.
     */
    public function scopeAwaitingPaymentProofUpload(Builder $query): Builder
    {
        return $query
            ->whereIn('tag', ['Pantry', 'General'])
            ->where('payment_workflow', self::PAYMENT_WORKFLOW_COMPANY_TRANSFER)
            ->where('status', 'Pending')
            ->where('approval_result', 'Approved')
            ->whereNotNull('purchase_attachment')
            ->whereNull('payment_proof_attachment');
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
            'requester_document_required', 'receipt_required' => $query->awaitingRequesterDocumentUpload(),
            'payment_proof_required' => $query->awaitingPaymentProofUpload(),
            'rejected' => $query->where('approval_result', 'Rejected'),
            'completed' => $query->whereNot(function (Builder $statusQuery): void {
                $statusQuery
                    ->where(function (Builder $reviewQuery): void {
                        $reviewQuery->awaitingReview();
                    })
                    ->orWhere(function (Builder $receiptQuery): void {
                        $receiptQuery->awaitingRequesterDocumentUpload();
                    })
                    ->orWhere(function (Builder $paymentProofQuery): void {
                        $paymentProofQuery->awaitingPaymentProofUpload();
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
            $documentLabel = $this->isDirectorCreditCardPayment()
                ? 'invoice'
                : ($this->isOwnExpensesPayment() ? 'receipt or invoice' : 'receipt');

            return [
                'label' => 'Approved — requester document required',
                'tone' => 'primary',
                'message' => "The requester must upload the {$documentLabel} to complete this claim.",
            ];
        }

        if ($this->canUploadPaymentProof()) {
            return [
                'label' => 'Approved — payment proof required',
                'tone' => 'primary',
                'message' => 'A Superadmin must upload the company proof of payment to complete this claim.',
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
