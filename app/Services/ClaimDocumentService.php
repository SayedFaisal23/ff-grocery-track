<?php

namespace App\Services;

use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Keeps all newly uploaded claim documents private while retaining access to
 * the historic public `attachments/` records that already exist in production.
 */
class ClaimDocumentService
{
    public const PRIVATE_DISK = 'local';
    public const PRIVATE_DIRECTORY = 'claim-documents';

    public const DOCUMENT_PURCHASE_ATTACHMENT = 'purchase_attachment';
    public const DOCUMENT_ATTACHMENT = 'attachment';
    public const DOCUMENT_PAYMENT_PROOF_ATTACHMENT = 'payment_proof_attachment';

    /**
     * Store a newly uploaded claim document on the private local disk.
     */
    public function store(UploadedFile $file): string
    {
        $path = $file->store(self::PRIVATE_DIRECTORY, self::PRIVATE_DISK);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the claim document.');
        }

        return $path;
    }

    /**
     * Determine whether a user is entitled to open a claim document.
     */
    public function canAccess(Tuntutan $claim, User $user): bool
    {
        return $user->hasRole('Superadmin') || $claim->user_id === $user->id;
    }

    /**
     * Resolve an existing document only when its path belongs to an approved
     * disk and the file is present. Paths are never passed through directly.
     *
     * @return array{disk: string, path: string, filename: string}|null
     */
    public function resolve(Tuntutan $claim, string $document): ?array
    {
        if (! $this->isSupportedDocument($document)) {
            return null;
        }

        $path = $claim->getAttribute($document);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if ($this->isPrivatePath($path)) {
            $disk = self::PRIVATE_DISK;
        } elseif ($document === self::DOCUMENT_ATTACHMENT && $this->isLegacyPublicPath($path)) {
            $disk = 'public';
        } else {
            return null;
        }

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => basename($path),
        ];
    }

    /**
     * Resolve a document for an authorised user and record Superadmin access
     * only after file existence has been verified.
     *
     * @return array{disk: string, path: string, filename: string}|null
     */
    public function openForUser(Tuntutan $claim, string $document, User $user): ?array
    {
        if (! $this->canAccess($claim, $user)) {
            return null;
        }

        $resolved = $this->resolve($claim, $document);

        if ($resolved === null) {
            return null;
        }

        if ($user->hasRole('Superadmin')) {
            $this->recordDocumentOpened($claim, $document, $user);
        }

        return $resolved;
    }

    /**
     * Return additive metadata for web and API claim payloads.
     *
     * @return array<string, bool|string|null>
     */
    public function documentMetadata(Tuntutan $claim): array
    {
        return [
            'purchase_attachment_available' => $this->resolve($claim, self::DOCUMENT_PURCHASE_ATTACHMENT) !== null,
            'attachment_available' => $this->resolve($claim, self::DOCUMENT_ATTACHMENT) !== null,
            'payment_proof_attachment_available' => $this->resolve($claim, self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT) !== null,
            'purchase_attachment_awaiting_view' => $claim->isDocumentAwaitingView(self::DOCUMENT_PURCHASE_ATTACHMENT),
            'attachment_awaiting_view' => $claim->isDocumentAwaitingView(self::DOCUMENT_ATTACHMENT),
            'payment_proof_attachment_awaiting_view' => $claim->isDocumentAwaitingView(self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT),
            'latest_attachment_downloaded_at' => $claim->latest_attachment_downloaded_at?->toIso8601String(),
            'claim_details_viewed_at' => $claim->claim_details_viewed_at?->toIso8601String(),
        ];
    }

    /**
     * Record the latest Superadmin claim-detail review.
     */
    public function recordClaimDetailsViewed(Tuntutan $claim, User $user): Tuntutan
    {
        if (! $user->hasRole('Superadmin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Only Superadmins may record claim-detail reviews.'
            );
        }

        return DB::transaction(function () use ($claim, $user) {
            $lockedClaim = Tuntutan::query()->lockForUpdate()->findOrFail($claim->id);

            $lockedClaim->update([
                'claim_details_viewed_by' => $user->id,
                'claim_details_viewed_at' => now(),
            ]);

            return $lockedClaim;
        });
    }

    /**
     * Record one document's first view and every latest attachment open.
     */
    private function recordDocumentOpened(Tuntutan $claim, string $document, User $user): void
    {
        $result = DB::transaction(function () use ($claim, $document, $user) {
            $lockedClaim = Tuntutan::query()->lockForUpdate()->findOrFail($claim->id);
            $viewedAtColumn = $lockedClaim->documentViewedAtColumn($document);
            $viewedByColumn = $lockedClaim->documentViewedByColumn($document);
            $now = now();
            $isFirstReceiptView = $document === self::DOCUMENT_ATTACHMENT
                && $lockedClaim->isAwaitingReceiptReview();
            $updates = [
                'latest_attachment_downloaded_by' => $user->id,
                'latest_attachment_downloaded_at' => $now,
            ];

            if ($lockedClaim->getAttribute($viewedAtColumn) === null) {
                $updates[$viewedByColumn] = $user->id;
                $updates[$viewedAtColumn] = $now;
            }

            if ($isFirstReceiptView) {
                $updates['receipt_viewed_by'] = $user->id;
                $updates['receipt_viewed_at'] = $now;
            }

            $oldData = $lockedClaim->toArray();
            $lockedClaim->update($updates);

            return [$oldData, $lockedClaim, $isFirstReceiptView];
        });

        [$oldData, $updatedClaim, $isFirstReceiptView] = $result;

        // Preserve the original receipt-notification audit event: it is only
        // emitted for the first Superadmin opening of a newly uploaded receipt.
        if ($isFirstReceiptView) {
            LogAktiviti::create([
                'user_id' => $user->id,
                'aktiviti' => "{$user->name} telah melihat resit permohonan ID {$updatedClaim->id} ({$updatedClaim->nama_item}).",
                'item_id' => null,
                'data_lama' => $oldData,
                'data_baru' => $updatedClaim->toArray(),
            ]);
        }
    }

    private function isSupportedDocument(string $document): bool
    {
        return in_array($document, [
            self::DOCUMENT_PURCHASE_ATTACHMENT,
            self::DOCUMENT_ATTACHMENT,
            self::DOCUMENT_PAYMENT_PROOF_ATTACHMENT,
        ], true);
    }

    private function isPrivatePath(string $path): bool
    {
        return $this->hasSafePrefix($path, self::PRIVATE_DIRECTORY.'/');
    }

    private function isLegacyPublicPath(string $path): bool
    {
        return $this->hasSafePrefix($path, 'attachments/');
    }

    private function hasSafePrefix(string $path, string $prefix): bool
    {
        return str_starts_with($path, $prefix)
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0");
    }
}
