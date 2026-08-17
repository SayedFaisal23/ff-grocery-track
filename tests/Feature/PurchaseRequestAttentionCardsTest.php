<?php

namespace Tests\Feature;

use App\Models\Tuntutan;
use App\Models\TuntutanPaymentProofView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseRequestAttentionCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Stocker']);
        Role::create(['name' => 'Tracker']);
    }

    public function test_superadmin_attention_cards_show_visible_workflow_actions_and_focus_links(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();

        $reviewClaims = collect(range(1, 4))
            ->map(fn (int $number) => $this->attentionClaim($stocker, "Approval request {$number}"));
        $paymentProofClaim = $this->attentionClaim($stocker, 'Company payment proof required', [
            'status' => 'Pending',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            'purchase_attachment' => 'claim-documents/company-quotation.pdf',
        ]);
        $receiptReviewClaim = $this->attentionClaim($stocker, 'Final receipt awaiting review', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'attachment' => 'claim-documents/final-receipt.pdf',
        ]);
        $reviewedReceipt = $this->attentionClaim($stocker, 'Reviewed final receipt', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'attachment' => 'claim-documents/reviewed-receipt.pdf',
            'receipt_viewed_at' => now(),
        ]);
        $preApprovalInvoice = $this->attentionClaim($stocker, 'Pre-approval invoice only', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_DIRECTOR_CC,
            'invoice_sent_to_account' => true,
            'purchase_attachment' => 'claim-documents/pre-approval-invoice.pdf',
        ]);

        $response = $this->actingAs($superadmin)->get(route('tuntutan.index'));
        $cards = $this->attentionCards($response);

        $this->assertSame(4, $cards['To be approved/rejected']['count']);
        $this->assertEqualsCanonicalizing(
            $reviewClaims->pluck('id')->all(),
            collect($cards['To be approved/rejected']['claims'])->pluck('id')->all(),
        );
        $this->assertSame(1, $cards['Proof of Payment to be uploaded']['count']);
        $this->assertSame([$paymentProofClaim->id], collect($cards['Proof of Payment to be uploaded']['claims'])->pluck('id')->all());
        $this->assertSame(1, $cards['Invoice/Receipt to be reviewed']['count']);
        $this->assertSame([$receiptReviewClaim->id], collect($cards['Invoice/Receipt to be reviewed']['claims'])->pluck('id')->all());

        $response
            ->assertOk()
            ->assertSee('purchase-request-attention-grid--three', false)
            ->assertSee('id="claim-'.$reviewClaims->first()->id.'"', false)
            ->assertSee('data-claim-card', false)
            ->assertSee('href="#claim-'.$reviewClaims->first()->id.'"', false)
            ->assertSee('data-claim-focus-link', false)
            ->assertSee('aria-controls="claim-'.$reviewClaims->first()->id.'"', false)
            ->assertSee('data-claim-focus-tone="warning"', false)
            ->assertSee('data-claim-focus-tone="primary"', false)
            ->assertSee('data-claim-focus-tone="success"', false)
            ->assertSee('class="purchase-request-attention-card" data-attention-tone="warning"', false)
            ->assertSee('class="purchase-request-attention-card" data-attention-tone="primary"', false)
            ->assertSee('class="purchase-request-attention-card" data-attention-tone="success"', false)
            ->assertDontSee('data-attention-pulse', false)
            ->assertSee('<details class="purchase-request-attention-card-more">', false)
            ->assertSee('+1 more', false)
            ->assertSee('href="#claim-'.$reviewClaims->last()->id.'"', false)
            ->assertDontSee('href="#claim-'.$reviewedReceipt->id.'"', false)
            ->assertDontSee('href="#claim-'.$preApprovalInvoice->id.'"', false);
    }

    public function test_stocker_attention_cards_only_include_their_own_visible_actions(): void
    {
        $stocker = $this->stocker();
        $otherStocker = $this->stocker();

        $requesterUpload = $this->attentionClaim($stocker, 'My invoice upload', [
            'status' => 'Pending',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ]);
        $paymentProof = $this->attentionClaim($stocker, 'My payment proof', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            'purchase_attachment' => 'claim-documents/my-quotation.pdf',
            'payment_proof_attachment' => 'claim-documents/my-payment-proof.pdf',
        ]);
        $pendingApproval = $this->attentionClaim($stocker, 'My pending approval');
        $otherUpload = $this->attentionClaim($otherStocker, 'Other invoice upload', [
            'status' => 'Pending',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ]);
        $otherProof = $this->attentionClaim($otherStocker, 'Other payment proof', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            'purchase_attachment' => 'claim-documents/other-quotation.pdf',
            'payment_proof_attachment' => 'claim-documents/other-payment-proof.pdf',
        ]);

        $response = $this->actingAs($stocker)->get(route('tuntutan.index'));
        $cards = $this->attentionCards($response);

        $this->assertSame(1, $cards['Invoice/Receipt to be uploaded']['count']);
        $this->assertSame([$requesterUpload->id], collect($cards['Invoice/Receipt to be uploaded']['claims'])->pluck('id')->all());
        $this->assertSame(1, $cards['Proof of Payment to be reviewed']['count']);
        $this->assertSame([$paymentProof->id], collect($cards['Proof of Payment to be reviewed']['claims'])->pluck('id')->all());

        $response
            ->assertOk()
            ->assertSee('purchase-request-attention-grid--two', false)
            ->assertDontSeeText('To be approved/rejected')
            ->assertDontSee('href="#claim-'.$pendingApproval->id.'"', false)
            ->assertDontSee('href="#claim-'.$otherUpload->id.'"', false)
            ->assertDontSee('href="#claim-'.$otherProof->id.'"', false);
    }

    public function test_attention_links_show_request_names_including_overflow_and_safe_fallbacks(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();
        $overflowClaim = $this->attentionClaim($stocker, 'Overflow printer toner');
        $unsafeName = 'Power & <script>alert("attention")</script>';
        $unsafeNameClaim = $this->attentionClaim($stocker, $unsafeName);
        $legacyNameClaim = $this->attentionClaim($stocker, 'Legacy request label', [
            'item_specification' => null,
        ]);
        $fallbackClaim = $this->attentionClaim($stocker, '', [
            'item_specification' => null,
        ]);

        $response = $this->actingAs($superadmin)->get(route('tuntutan.index'));
        $approvalClaims = $this->attentionCards($response)['To be approved/rejected']['claims'];

        $this->assertSame([
            "Claim #{$fallbackClaim->id}",
            'Legacy request label',
            $unsafeName,
            'Overflow printer toner',
        ], collect($approvalClaims)->pluck('label')->all());
        $response
            ->assertOk()
            ->assertSee('<details class="purchase-request-attention-card-more">', false)
            ->assertSee('+1 more', false);

        $unsafeLink = $this->attentionLinkMarkup($response, $unsafeNameClaim);
        $this->assertStringContainsString(e($unsafeName), $unsafeLink);
        $this->assertStringNotContainsString($unsafeName, $unsafeLink);
        $this->assertStringContainsString(
            'aria-label="Focus Claim #'.$unsafeNameClaim->id.': '.e($unsafeName).'"',
            $unsafeLink,
        );

        $legacyLink = $this->attentionLinkMarkup($response, $legacyNameClaim);
        $this->assertStringContainsString('>Legacy request label</span>', $legacyLink);
        $this->assertStringNotContainsString('>Claim #'.$legacyNameClaim->id.'</span>', $legacyLink);

        $fallbackLink = $this->attentionLinkMarkup($response, $fallbackClaim);
        $this->assertStringContainsString('>Claim #'.$fallbackClaim->id.'</span>', $fallbackLink);

        $overflowLink = $this->attentionLinkMarkup($response, $overflowClaim);
        $this->assertStringContainsString('>Overflow printer toner</span>', $overflowLink);
        $this->assertStringContainsString('data-claim-focus-link', $overflowLink);
        $this->assertStringContainsString('aria-controls="claim-'.$overflowClaim->id.'"', $overflowLink);
    }

    public function test_attention_cards_follow_the_current_request_filters(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();
        $visibleClaim = $this->attentionClaim($stocker, 'Visible filtered request', [
            'tag' => 'Pantry',
            'minggu_tuntutan' => '2026-W32',
        ]);
        $differentType = $this->attentionClaim($stocker, 'General filtered out', [
            'tag' => 'General',
            'minggu_tuntutan' => '2026-W32',
        ]);
        $differentStatus = $this->attentionClaim($stocker, 'Proof filtered out', [
            'tag' => 'Pantry',
            'minggu_tuntutan' => '2026-W32',
            'status' => 'Pending',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            'purchase_attachment' => 'claim-documents/filtered-quotation.pdf',
        ]);

        $response = $this->actingAs($superadmin)->get(route('tuntutan.index', [
            'weeks' => ['2026-W32'],
            'type' => 'Pantry',
            'status' => 'submitted',
        ]));
        $cards = $this->attentionCards($response);

        $this->assertSame(1, $cards['To be approved/rejected']['count']);
        $this->assertSame([$visibleClaim->id], collect($cards['To be approved/rejected']['claims'])->pluck('id')->all());
        $this->assertSame(0, $cards['Proof of Payment to be uploaded']['count']);
        $this->assertSame(0, $cards['Invoice/Receipt to be reviewed']['count']);

        $response
            ->assertOk()
            ->assertSee('href="#claim-'.$visibleClaim->id.'"', false)
            ->assertSee('No matching requests require attention.')
            ->assertDontSee('href="#claim-'.$differentType->id.'"', false)
            ->assertDontSee('href="#claim-'.$differentStatus->id.'"', false);
    }

    public function test_missing_owner_payment_proof_does_not_mark_it_reviewed(): void
    {
        Storage::fake('local');

        $owner = $this->stocker();
        $claim = $this->attentionClaim($owner, 'Missing owner payment proof', [
            'status' => 'Completed',
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
            'payment_proof_attachment' => 'claim-documents/missing-owner-payment-proof.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('tuntutan.payment-proof', $claim))
            ->assertNotFound();

        $this->assertDatabaseMissing('tuntutan_payment_proof_views', [
            'tuntutan_id' => $claim->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_owner_payment_proof_view_is_personal_idempotent_and_clears_only_their_attention(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-08-03 10:00:00');

        try {
            $owner = $this->stocker('owner-payment-proof-token');
            $otherStocker = $this->stocker('other-payment-proof-token');
            $superadmin = $this->superadmin('superadmin-payment-proof-token');
            $paymentProofPath = 'claim-documents/owner-review-payment-proof.pdf';
            Storage::disk('local')->put($paymentProofPath, 'payment proof');

            $claim = $this->attentionClaim($owner, 'Owner payment proof review', [
                'status' => 'Completed',
                'approval_result' => 'Approved',
                'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_COMPANY_TRANSFER,
                'purchase_attachment' => 'claim-documents/owner-review-quotation.pdf',
                'payment_proof_attachment' => $paymentProofPath,
            ]);

            $initialOwnerResponse = $this->actingAs($owner)->get(route('tuntutan.index'));
            $initialOwnerCards = $this->attentionCards($initialOwnerResponse);

            $this->assertSame(1, $initialOwnerCards['Proof of Payment to be reviewed']['count']);
            $this->assertSame([$claim->id], collect($initialOwnerCards['Proof of Payment to be reviewed']['claims'])->pluck('id')->all());
            $initialOwnerResponse
                ->assertOk()
                ->assertSee('href="#claim-'.$claim->id.'"', false)
                ->assertSee('window.location.reload()', false);
            $this->assertStringContainsString(
                'data-payment-proof-review-link',
                $this->paymentProofLinkMarkup($initialOwnerResponse, $claim),
            );

            $this->withToken('other-payment-proof-token')
                ->get("/api/tuntutan/{$claim->id}/payment-proof")
                ->assertForbidden();
            $this->assertDatabaseMissing('tuntutan_payment_proof_views', [
                'tuntutan_id' => $claim->id,
                'user_id' => $otherStocker->id,
            ]);
            $this->assertDatabaseMissing('tuntutan_payment_proof_views', [
                'tuntutan_id' => $claim->id,
                'user_id' => $owner->id,
            ]);

            $superadminResponse = $this->withToken('superadmin-payment-proof-token')
                ->get("/api/tuntutan/{$claim->id}/payment-proof");
            $superadminResponse
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            $claim->refresh();
            $this->assertSame($superadmin->id, $claim->payment_proof_attachment_viewed_by);
            $this->assertNotNull($claim->payment_proof_attachment_viewed_at);
            $superadminViewedAt = $claim->payment_proof_attachment_viewed_at->toDateTimeString();
            $this->assertDatabaseMissing('tuntutan_payment_proof_views', [
                'tuntutan_id' => $claim->id,
                'user_id' => $owner->id,
            ]);

            $superadminIndexResponse = $this->actingAs($superadmin)->get(route('tuntutan.index'));
            $superadminIndexResponse->assertOk();
            $this->assertStringNotContainsString(
                'data-payment-proof-review-link',
                $this->paymentProofLinkMarkup($superadminIndexResponse, $claim),
            );

            $ownerAfterSuperadminResponse = $this->actingAs($owner)->get(route('tuntutan.index'));
            $ownerAfterSuperadminCards = $this->attentionCards($ownerAfterSuperadminResponse);
            $this->assertSame(1, $ownerAfterSuperadminCards['Proof of Payment to be reviewed']['count']);
            $ownerAfterSuperadminResponse
                ->assertSee('href="#claim-'.$claim->id.'"', false);
            $this->assertStringContainsString(
                'data-payment-proof-review-link',
                $this->paymentProofLinkMarkup($ownerAfterSuperadminResponse, $claim),
            );

            $this->withToken('owner-payment-proof-token')
                ->get("/api/tuntutan/{$claim->id}/payment-proof")
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            $ownerView = TuntutanPaymentProofView::query()
                ->where('tuntutan_id', $claim->id)
                ->where('user_id', $owner->id)
                ->sole();
            $this->assertNotNull($ownerView->viewed_at);
            $firstOwnerViewedAt = $ownerView->viewed_at->toDateTimeString();
            $this->assertTrue($claim->fresh()->paymentProofViews()->whereKey($ownerView->id)->exists());

            $claim->refresh();
            $this->assertSame($superadmin->id, $claim->payment_proof_attachment_viewed_by);
            $this->assertSame($superadminViewedAt, $claim->payment_proof_attachment_viewed_at->toDateTimeString());

            Carbon::setTestNow('2026-08-03 10:05:00');
            $this->actingAs($owner)
                ->get(route('tuntutan.payment-proof', $claim))
                ->assertOk();

            $ownerView->refresh();
            $this->assertSame($firstOwnerViewedAt, $ownerView->viewed_at->toDateTimeString());
            $this->assertSame(1, TuntutanPaymentProofView::query()
                ->where('tuntutan_id', $claim->id)
                ->where('user_id', $owner->id)
                ->count());

            $ownerAfterViewResponse = $this->actingAs($owner)->get(route('tuntutan.index'));
            $ownerAfterViewCards = $this->attentionCards($ownerAfterViewResponse);
            $this->assertSame(0, $ownerAfterViewCards['Proof of Payment to be reviewed']['count']);
            $this->assertSame([], $ownerAfterViewCards['Proof of Payment to be reviewed']['claims']);
            $ownerAfterViewResponse->assertDontSee('href="#claim-'.$claim->id.'"', false);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return Collection<string, array{title: string, tone: string, icon: string, count: int, claims: array<int, array{id: int, label: string}>}>
     */
    private function attentionCards($response): Collection
    {
        return collect($response->viewData('attentionCards'))->keyBy('title');
    }

    private function paymentProofLinkMarkup($response, Tuntutan $claim): string
    {
        $matchCount = preg_match(
            '#<a\\b(?=[^>]*\\bhref="[^"]*/tuntutan/'.preg_quote((string) $claim->getKey(), '#').'/payment-proof")[^>]*>#',
            $response->getContent(),
            $matches,
        );

        $this->assertSame(1, $matchCount, 'Expected the payment-proof action link to be rendered.');

        return $matches[0];
    }

    private function attentionLinkMarkup($response, Tuntutan $claim): string
    {
        $matchCount = preg_match(
            '~<a\\b(?=[^>]*\\bhref="#claim-'.preg_quote((string) $claim->getKey(), '~').'\")[^>]*>.*?</a>~s',
            $response->getContent(),
            $matches,
        );

        $this->assertSame(1, $matchCount, 'Expected the attention-card link to be rendered.');

        return $matches[0];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function attentionClaim(User $owner, string $name, array $overrides = []): Tuntutan
    {
        return Tuntutan::create(array_merge([
            'user_id' => $owner->id,
            'requestor_name' => $owner->name,
            'nama_item' => $name,
            'item_specification' => $name,
            'tag' => 'Pantry',
            'nilai_tuntutan' => 10.00,
            'total_item_amount' => 10.00,
            'tarikh_beli' => '2026-08-03',
            'request_date' => '2026-08-03',
            'date_receive' => '2026-08-03',
            'minggu_tuntutan' => '2026-W32',
            'status' => 'Pending',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_LEGACY,
        ], $overrides));
    }

    private function superadmin(?string $apiToken = null): User
    {
        $user = User::factory()->create(['api_token' => $apiToken]);
        $user->assignRole('Superadmin');

        return $user;
    }

    private function stocker(?string $apiToken = null): User
    {
        $user = User::factory()->create(['api_token' => $apiToken]);
        $user->assignRole('Stocker');

        return $user;
    }
}
