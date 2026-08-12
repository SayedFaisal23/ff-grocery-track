<?php

namespace Tests\Feature;

use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseRequestPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Stocker']);
        Role::create(['name' => 'Tracker']);
    }

    public function test_director_cc_with_invoice_sent_requires_an_initial_invoice_and_completes_after_approval(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->purchasePlatform();
        $this->paymentPreset('Director CC', 'director_cc');
        $this->paymentPreset('Unconfigured Card', null);

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $this->purchasePayload('Unconfigured Card'))
            ->assertSessionHasErrors('payment_method');

        $payload = $this->purchasePayload('Director CC', [
            'invoice_sent_to_account' => true,
        ]);

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $payload)
            ->assertSessionHasErrors('purchase_attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'attachment' => UploadedFile::fake()->create('receipt-too-early.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'purchase_attachment' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();
        $this->assertSame('director_cc', $claim->payment_workflow);
        $this->assertNotNull($claim->purchase_attachment);
        $this->assertWorkflow($claim, 'director_cc', 'awaiting_approval', 'superadmin');
        Storage::disk('local')->assertExists($claim->purchase_attachment);

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Approved', $claim->approval_result);
        $this->assertSame('Completed', $claim->status);
        $this->assertNull($claim->attachment);
        $this->assertNull($claim->payment_proof_attachment);
        $this->assertWorkflow($claim, 'director_cc', 'completed');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('late-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $this->assertNull($claim->fresh()->attachment);
    }

    public function test_director_cc_without_invoice_sent_waits_for_the_requester_invoice_after_approval(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->purchasePlatform();
        $this->paymentPreset('Director CC', 'director_cc');

        $payload = $this->purchasePayload('Director CC', [
            'invoice_sent_to_account' => false,
        ]);

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'purchase_attachment' => UploadedFile::fake()->create('invoice-too-early.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('purchase_attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $payload)
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();
        $this->assertSame('director_cc', $claim->payment_workflow);
        $this->assertNull($claim->purchase_attachment);

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Pending', $claim->status);
        $this->assertSame('Approved', $claim->approval_result);
        $this->assertWorkflow($claim, 'director_cc', 'awaiting_requester_document', 'requester', 'attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('director-invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Completed', $claim->status);
        $this->assertNotNull($claim->attachment);
        $this->assertWorkflow($claim, 'director_cc', 'completed');
        Storage::disk('local')->assertExists($claim->attachment);
    }

    public function test_own_expenses_waits_for_a_requester_receipt_after_approval(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->purchasePlatform();

        $payload = $this->purchasePayload(Tuntutan::OTHER_PAYMENT_METHOD);

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'invoice_sent_to_account' => true,
                'purchase_attachment' => UploadedFile::fake()->create('not-required.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['invoice_sent_to_account', 'purchase_attachment']);

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $payload)
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();
        $this->assertSame('own_expenses', $claim->payment_workflow);
        $this->assertSame(Tuntutan::OTHER_PAYMENT_METHOD_DETAIL, $claim->other_payment_method);

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Pending', $claim->status);
        $this->assertWorkflow($claim, 'own_expenses', 'awaiting_requester_document', 'requester', 'attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('own-expenses-receipt.png', 100, 'image/png'),
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Completed', $claim->status);
        $this->assertNotNull($claim->attachment);
        $this->assertWorkflow($claim, 'own_expenses', 'completed');
    }

    public function test_company_transfer_requires_a_preapproval_document_then_admin_payment_proof(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $otherStocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->purchasePlatform();
        $this->paymentPreset('Company Transfer', 'company_transfer');

        $payload = $this->purchasePayload('Company Transfer');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $payload)
            ->assertSessionHasErrors('purchase_attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'invoice_sent_to_account' => true,
                'payment_proof_attachment' => UploadedFile::fake()->create('proof-too-early.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('payment_proof_attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'invoice_sent_to_account' => true,
                'purchase_attachment' => UploadedFile::fake()->create('company-invoice.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('invoice_sent_to_account');

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), array_merge($payload, [
                'purchase_attachment' => UploadedFile::fake()->create('company-quotation.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();
        $this->assertSame('company_transfer', $claim->payment_workflow);
        $this->assertNotNull($claim->purchase_attachment);

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Pending', $claim->status);
        $this->assertSame('Approved', $claim->approval_result);
        $this->assertWorkflow($claim, 'company_transfer', 'awaiting_payment_proof', 'superadmin', 'payment_proof_attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('requester-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $this->actingAs($stocker)
            ->post(route('tuntutan.payment-proof.store', $claim), [
                'payment_proof_attachment' => UploadedFile::fake()->create('forbidden-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->post(route('tuntutan.payment-proof.store', $claim), [
                'payment_proof_attachment' => UploadedFile::fake()->create('bank-transfer-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Completed', $claim->status);
        $this->assertNotNull($claim->payment_proof_attachment);
        $this->assertWorkflow($claim, 'company_transfer', 'completed');
        Storage::disk('local')->assertExists($claim->payment_proof_attachment);
        $originalProof = $claim->payment_proof_attachment;

        $this->actingAs($superadmin)
            ->post(route('tuntutan.payment-proof.store', $claim), [
                'payment_proof_attachment' => UploadedFile::fake()->create('replacement-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $this->assertSame($originalProof, $claim->fresh()->payment_proof_attachment);

        $proofLog = LogAktiviti::query()
            ->where('user_id', $superadmin->id)
            ->get()
            ->first(fn (LogAktiviti $log): bool => ($log->data_baru['payment_proof_attachment'] ?? null) === $claim->payment_proof_attachment);
        $this->assertNotNull($proofLog);

        $this->actingAs($otherStocker)
            ->get(route('tuntutan.payment-proof', $claim))
            ->assertForbidden();

        $this->actingAs($stocker)
            ->get(route('tuntutan.payment-proof', $claim))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($superadmin)
            ->get(route('tuntutan.payment-proof', $claim))
            ->assertOk();

        $claim->refresh();
        $this->assertSame($superadmin->id, $claim->payment_proof_attachment_viewed_by);
        $this->assertNotNull($claim->payment_proof_attachment_viewed_at);
    }

    public function test_rejected_workflow_is_terminal_and_does_not_accept_later_documents(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->purchasePlatform();

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $this->purchasePayload(Tuntutan::OTHER_PAYMENT_METHOD))
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Rejected'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Completed', $claim->status);
        $this->assertSame('Rejected', $claim->approval_result);
        $this->assertWorkflow($claim, 'own_expenses', 'rejected');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('rejected-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('error');

        $this->assertNull($claim->fresh()->attachment);
    }

    public function test_web_filters_distinguish_requester_documents_from_company_payment_proofs(): void
    {
        $stocker = $this->stocker();
        $superadmin = $this->superadmin();

        $this->workflowClaim($stocker, 'Director invoice needed', [
            'payment_workflow' => 'director_cc',
            'invoice_sent_to_account' => false,
            'status' => 'Pending',
            'approval_result' => 'Approved',
        ]);
        $this->workflowClaim($stocker, 'Own expense receipt needed', [
            'payment_workflow' => 'own_expenses',
            'status' => 'Pending',
            'approval_result' => 'Approved',
        ]);
        $this->workflowClaim($stocker, 'Company proof needed', [
            'payment_workflow' => 'company_transfer',
            'purchase_attachment' => 'claim-documents/company-quotation.pdf',
            'status' => 'Pending',
            'approval_result' => 'Approved',
        ]);
        $this->workflowClaim($stocker, 'Completed director request', [
            'payment_workflow' => 'director_cc',
            'invoice_sent_to_account' => true,
            'purchase_attachment' => 'claim-documents/invoice.pdf',
            'status' => 'Completed',
            'approval_result' => 'Approved',
        ]);
        $this->workflowClaim($stocker, 'Rejected company request', [
            'payment_workflow' => 'company_transfer',
            'status' => 'Completed',
            'approval_result' => 'Rejected',
        ]);

        $this->actingAs($superadmin)
            ->get('/tuntutan?status=requester_document_required')
            ->assertOk()
            ->assertSeeText('Director invoice needed')
            ->assertSeeText('Own expense receipt needed')
            ->assertDontSeeText('Company proof needed')
            ->assertDontSeeText('Completed director request')
            ->assertDontSeeText('Rejected company request');

        $this->actingAs($superadmin)
            ->get('/tuntutan?status=payment_proof_required')
            ->assertOk()
            ->assertSeeText('Company proof needed')
            ->assertDontSeeText('Director invoice needed')
            ->assertDontSeeText('Own expense receipt needed')
            ->assertDontSeeText('Completed director request')
            ->assertDontSeeText('Rejected company request');

        $this->actingAs($superadmin)
            ->get('/tuntutan?status=receipt_required')
            ->assertOk()
            ->assertSeeText('Director invoice needed')
            ->assertSeeText('Own expense receipt needed')
            ->assertDontSeeText('Company proof needed');
    }

    public function test_api_exposes_workflow_and_enforces_company_payment_proof_authorization(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker('payment-api-stocker');
        $otherStocker = $this->stocker('payment-api-other-stocker');
        $superadmin = $this->superadmin('payment-api-superadmin');
        $this->purchasePlatform();
        $this->paymentPreset('Director CC', 'director_cc');
        $this->paymentPreset('Company Transfer', 'company_transfer');
        $this->paymentPreset('Needs Configuration', null);

        $this->withToken('payment-api-stocker')
            ->get('/api/tuntutan-preset')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Director CC',
                'payment_workflow' => 'director_cc',
            ])
            ->assertJsonFragment([
                'name' => 'Company Transfer',
                'payment_workflow' => 'company_transfer',
            ])
            ->assertJsonMissing(['name' => 'Needs Configuration']);

        $this->withToken('payment-api-superadmin')
            ->get('/api/tuntutan-preset')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Needs Configuration',
                'payment_workflow' => null,
            ]);

        $this->withToken('payment-api-stocker')
            ->post('/api/tuntutan', $this->purchasePayload('Needs Configuration'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $companyPayload = $this->purchasePayload('Company Transfer');

        $this->withToken('payment-api-stocker')
            ->post('/api/tuntutan', $companyPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchase_attachment');

        $createResponse = $this->withToken('payment-api-stocker')
            ->post('/api/tuntutan', array_merge($companyPayload, [
                'purchase_attachment' => UploadedFile::fake()->create('api-company-quotation.pdf', 100, 'application/pdf'),
            ]))
            ->assertCreated()
            ->assertJsonPath('workflow.type', 'company_transfer')
            ->assertJsonStructure([
                'workflow' => ['type', 'stage', 'next_actor', 'required_document'],
                'documents' => [
                    'purchase_attachment' => ['available', 'url'],
                    'attachment' => ['available', 'url'],
                    'payment_proof_attachment' => ['available', 'url'],
                ],
            ]);

        $claimId = $createResponse->json('id');

        $this->withToken('payment-api-superadmin')
            ->patchJson("/api/tuntutan/{$claimId}/status", ['approval_result' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('status', 'Pending')
            ->assertJsonPath('workflow.type', 'company_transfer')
            ->assertJsonPath('workflow.stage', 'awaiting_payment_proof')
            ->assertJsonPath('workflow.next_actor', 'superadmin')
            ->assertJsonPath('workflow.required_document', 'payment_proof_attachment');

        $this->withToken('payment-api-stocker')
            ->post("/api/tuntutan/{$claimId}/payment-proof", [
                'payment_proof_attachment' => UploadedFile::fake()->create('owner-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->withToken('payment-api-superadmin')
            ->post("/api/tuntutan/{$claimId}/payment-proof", [
                'payment_proof_attachment' => UploadedFile::fake()->create('invalid-proof.exe', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_proof_attachment');

        $this->withToken('payment-api-superadmin')
            ->post("/api/tuntutan/{$claimId}/payment-proof", [
                'payment_proof_attachment' => UploadedFile::fake()->create('api-bank-proof.pdf', 100, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('workflow.stage', 'completed')
            ->assertJsonPath('documents.payment_proof_attachment.available', true);

        $this->withToken('payment-api-stocker')
            ->get("/api/tuntutan/{$claimId}/payment-proof")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->withToken('payment-api-other-stocker')
            ->get("/api/tuntutan/{$claimId}/payment-proof")
            ->assertForbidden();
    }

    public function test_api_completes_director_card_and_own_expenses_workflows(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker('remaining-api-stocker');
        $superadmin = $this->superadmin('remaining-api-superadmin');
        $this->purchasePlatform();
        $this->paymentPreset('Director CC', 'director_cc');

        $directorResponse = $this->withToken('remaining-api-stocker')
            ->post('/api/tuntutan', array_merge($this->purchasePayload('Director CC', [
                'invoice_sent_to_account' => true,
            ]), [
                'purchase_attachment' => UploadedFile::fake()->create('director-api-invoice.pdf', 100, 'application/pdf'),
            ]))
            ->assertCreated()
            ->assertJsonPath('workflow.type', 'director_cc')
            ->assertJsonPath('workflow.stage', 'awaiting_approval');

        $directorClaimId = $directorResponse->json('id');

        $this->withToken('remaining-api-superadmin')
            ->patchJson("/api/tuntutan/{$directorClaimId}/status", ['approval_result' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('workflow.stage', 'completed');

        $this->withToken('remaining-api-stocker')
            ->post("/api/tuntutan/{$directorClaimId}/lampiran", [
                'attachment' => UploadedFile::fake()->create('late-director-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertConflict();

        $ownExpensesResponse = $this->withToken('remaining-api-stocker')
            ->post('/api/tuntutan', $this->purchasePayload(Tuntutan::OTHER_PAYMENT_METHOD))
            ->assertCreated()
            ->assertJsonPath('workflow.type', 'own_expenses')
            ->assertJsonPath('workflow.stage', 'awaiting_approval');

        $ownExpensesClaimId = $ownExpensesResponse->json('id');

        $this->withToken('remaining-api-superadmin')
            ->patchJson("/api/tuntutan/{$ownExpensesClaimId}/status", ['approval_result' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('status', 'Pending')
            ->assertJsonPath('workflow.stage', 'awaiting_requester_document')
            ->assertJsonPath('workflow.next_actor', 'requester')
            ->assertJsonPath('workflow.required_document', 'attachment');

        $this->withToken('remaining-api-stocker')
            ->post("/api/tuntutan/{$ownExpensesClaimId}/lampiran", [
                'attachment' => UploadedFile::fake()->create('own-expenses-api-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('workflow.stage', 'completed');

        $this->assertSame($stocker->id, Tuntutan::query()->findOrFail($ownExpensesClaimId)->user_id);
    }

    public function test_api_snapshots_each_workflow_and_legacy_claims_keep_their_receipt_flow(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker('workflow-snapshot-stocker');
        $superadmin = $this->superadmin('workflow-snapshot-superadmin');
        $this->purchasePlatform();
        $preset = $this->paymentPreset('Director CC', 'director_cc');

        $response = $this->withToken('workflow-snapshot-stocker')
            ->post('/api/tuntutan', $this->purchasePayload('Director CC', [
                'invoice_sent_to_account' => false,
            ]))
            ->assertCreated()
            ->assertJsonPath('workflow.type', 'director_cc')
            ->assertJsonPath('workflow.stage', 'awaiting_approval');

        $claim = Tuntutan::query()->findOrFail($response->json('id'));
        $preset->update(['payment_workflow' => 'company_transfer']);

        $this->assertSame('director_cc', $claim->fresh()->payment_workflow);

        $this->withToken('workflow-snapshot-superadmin')
            ->patchJson("/api/tuntutan/{$claim->id}/status", ['approval_result' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('workflow.type', 'director_cc')
            ->assertJsonPath('workflow.stage', 'awaiting_requester_document')
            ->assertJsonPath('workflow.next_actor', 'requester')
            ->assertJsonPath('workflow.required_document', 'attachment');

        $legacy = $this->legacyPurchaseClaim($stocker);
        $this->assertWorkflow($legacy, 'legacy', 'awaiting_approval', 'superadmin');

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $legacy), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $legacy->refresh();
        $this->assertSame('Pending', $legacy->status);
        $this->assertWorkflow($legacy, 'legacy', 'awaiting_requester_document', 'requester', 'attachment');

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $legacy), [
                'attachment' => UploadedFile::fake()->create('legacy-receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame('Completed', $legacy->fresh()->status);
    }

    private function stocker(?string $apiToken = null): User
    {
        $user = User::factory()->create(['api_token' => $apiToken]);
        $user->assignRole('Stocker');

        return $user;
    }

    private function superadmin(?string $apiToken = null): User
    {
        $user = User::factory()->create(['api_token' => $apiToken]);
        $user->assignRole('Superadmin');

        return $user;
    }

    private function purchasePlatform(): TuntutanPreset
    {
        return TuntutanPreset::create([
            'type' => TuntutanPreset::TYPE_PURCHASE_PLATFORM,
            'name' => 'Shopee',
            'sort_order' => 1,
        ]);
    }

    private function paymentPreset(string $name, ?string $workflow): TuntutanPreset
    {
        return TuntutanPreset::create([
            'type' => TuntutanPreset::TYPE_PAYMENT_METHOD,
            'name' => $name,
            'payment_workflow' => $workflow,
            'sort_order' => TuntutanPreset::query()->forType(TuntutanPreset::TYPE_PAYMENT_METHOD)->count() + 1,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function purchasePayload(string $paymentMethod, array $overrides = []): array
    {
        $today = now()->toDateString();

        return array_merge([
            'tag' => 'Pantry',
            'request_date' => $today,
            'item_specification' => 'Monthly pantry supplies',
            'purchase_purpose' => 'Maintain pantry stock.',
            'invoice_no' => 'PR-1001',
            'purchase_platform' => 'Shopee',
            'total_item_amount' => 45.90,
            'payment_method' => $paymentMethod,
            'date_receive' => $today,
        ], $overrides);
    }

    private function legacyPurchaseClaim(User $owner): Tuntutan
    {
        return Tuntutan::create([
            'user_id' => $owner->id,
            'requestor_name' => $owner->name,
            'nama_item' => 'Historic purchase request',
            'item_specification' => 'Historic purchase request',
            'purchase_purpose' => 'Regression coverage',
            'purchase_platform' => 'Historic platform',
            'tag' => 'Pantry',
            'nilai_tuntutan' => 10.00,
            'total_item_amount' => 10.00,
            'payment_method' => 'Historic payment method',
            'payment_workflow' => 'legacy',
            'tarikh_beli' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'date_receive' => now()->toDateString(),
            'minggu_tuntutan' => now()->format('o-\\WW'),
            'status' => 'Pending',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function workflowClaim(User $owner, string $name, array $overrides): Tuntutan
    {
        return Tuntutan::create(array_merge([
            'user_id' => $owner->id,
            'requestor_name' => $owner->name,
            'nama_item' => $name,
            'item_specification' => $name,
            'purchase_purpose' => 'Filter regression coverage',
            'purchase_platform' => 'Shopee',
            'tag' => 'Pantry',
            'nilai_tuntutan' => 10.00,
            'total_item_amount' => 10.00,
            'payment_method' => 'Configured method',
            'tarikh_beli' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'date_receive' => now()->toDateString(),
            'minggu_tuntutan' => now()->format('o-\\WW'),
        ], $overrides));
    }

    private function assertWorkflow(
        Tuntutan $claim,
        string $type,
        string $stage,
        ?string $nextActor = null,
        ?string $requiredDocument = null,
    ): void {
        $workflow = $claim->workflow();

        $this->assertSame($type, $workflow['type']);
        $this->assertSame($stage, $workflow['stage']);
        $this->assertArrayHasKey('next_actor', $workflow);
        $this->assertArrayHasKey('required_document', $workflow);

        if ($nextActor !== null) {
            $this->assertSame($nextActor, $workflow['next_actor']);
        }

        if ($requiredDocument !== null) {
            $this->assertSame($requiredDocument, $workflow['required_document']);
        }
    }
}
