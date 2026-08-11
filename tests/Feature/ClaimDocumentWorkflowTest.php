<?php

namespace Tests\Feature;

use App\Models\Tuntutan;
use App\Models\TuntutanPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClaimDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Stocker']);
        Role::create(['name' => 'Tracker']);
    }

    public function test_purchase_request_keeps_a_private_supporting_document_and_requires_a_later_receipt(): void
    {
        Storage::fake('local');

        $stocker = $this->stocker();
        $superadmin = $this->superadmin();
        $this->createPurchasePresets();

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $this->purchasePayload([
                'purchase_attachment' => UploadedFile::fake()->create('quotation.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('tuntutan.index'));

        $claim = Tuntutan::query()->sole();

        $this->assertSame('Pending', $claim->status);
        $this->assertNull($claim->approval_result);
        $this->assertNull($claim->attachment);
        $this->assertNotNull($claim->purchase_attachment);
        $this->assertStringStartsWith('claim-documents/', $claim->purchase_attachment);
        Storage::disk('local')->assertExists($claim->purchase_attachment);

        $this->actingAs($superadmin)
            ->patch(route('tuntutan.status', $claim), ['approval_result' => 'Approved'])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Pending', $claim->status);
        $this->assertSame('Approved', $claim->approval_result);
        $this->assertTrue($claim->canUploadAttachment());

        $this->actingAs($stocker)
            ->get(route('tuntutan.index'))
            ->assertOk()
            ->assertSee('data-file-submit', false)
            ->assertSee('aria-disabled="true"', false);

        $this->actingAs($stocker)
            ->post(route('tuntutan.attachment.store', $claim), [
                'attachment' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $claim->refresh();
        $this->assertSame('Completed', $claim->status);
        $this->assertNotNull($claim->attachment);
        $this->assertNotSame($claim->purchase_attachment, $claim->attachment);
        $this->assertStringStartsWith('claim-documents/', $claim->attachment);
        Storage::disk('local')->assertExists($claim->attachment);
    }

    public function test_superadmin_document_opening_tracks_each_private_file_and_latest_access(): void
    {
        Storage::fake('local');

        $owner = $this->stocker();
        $otherStocker = $this->stocker();
        $superadmin = $this->superadmin();
        Storage::disk('local')->put('claim-documents/quotation.pdf', 'quotation');
        Storage::disk('local')->put('claim-documents/receipt.pdf', 'receipt');

        $claim = $this->purchaseClaim($owner, [
            'purchase_attachment' => 'claim-documents/quotation.pdf',
            'attachment' => 'claim-documents/receipt.pdf',
            'status' => 'Completed',
            'approval_result' => 'Approved',
        ]);

        $this->actingAs($superadmin)
            ->get(route('tuntutan.index'))
            ->assertOk()
            ->assertSee('claim-document-awaiting-view', false)
            ->assertSeeText('Latest attachment download date and time')
            ->assertSeeText('Latest claim details review date and time');

        $this->actingAs($otherStocker)
            ->get(route('tuntutan.purchase-attachment', $claim))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get(route('tuntutan.purchase-attachment', $claim))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $claim->refresh();
        $this->assertSame($superadmin->id, $claim->purchase_attachment_viewed_by);
        $this->assertNotNull($claim->purchase_attachment_viewed_at);
        $this->assertSame($superadmin->id, $claim->latest_attachment_downloaded_by);
        $this->assertNotNull($claim->latest_attachment_downloaded_at);
        $this->assertTrue($claim->isDocumentAwaitingView('attachment'));
        $this->assertFalse($claim->isDocumentAwaitingView('purchase_attachment'));

        $this->actingAs($superadmin)
            ->get(route('tuntutan.attachment', $claim))
            ->assertOk();

        $claim->refresh();
        $this->assertSame($superadmin->id, $claim->attachment_viewed_by);
        $this->assertNotNull($claim->attachment_viewed_at);
        $this->assertSame($superadmin->id, $claim->receipt_viewed_by);
        $this->assertNotNull($claim->receipt_viewed_at);
    }

    public function test_admin_detail_review_endpoint_is_restricted_and_updates_latest_review_time(): void
    {
        $owner = $this->stocker();
        $superadmin = $this->superadmin();
        $claim = $this->purchaseClaim($owner);

        $this->actingAs($owner)
            ->post(route('tuntutan.details-viewed', $claim))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->post(route('tuntutan.details-viewed', $claim))
            ->assertOk()
            ->assertJsonStructure([
                'claim_details_viewed_at',
                'claim_details_viewed_at_display',
            ]);

        $claim->refresh();
        $this->assertSame($superadmin->id, $claim->claim_details_viewed_by);
        $this->assertNotNull($claim->claim_details_viewed_at);
    }

    public function test_missing_private_document_does_not_create_an_attachment_view_record(): void
    {
        $owner = $this->stocker();
        $superadmin = $this->superadmin();
        $claim = $this->purchaseClaim($owner, [
            'purchase_attachment' => 'claim-documents/missing-quotation.pdf',
        ]);

        $this->actingAs($superadmin)
            ->get(route('tuntutan.purchase-attachment', $claim))
            ->assertNotFound();

        $claim->refresh();
        $this->assertNull($claim->purchase_attachment_viewed_at);
        $this->assertNull($claim->latest_attachment_downloaded_at);
    }

    public function test_api_accepts_and_serves_a_private_purchase_attachment_with_document_metadata(): void
    {
        Storage::fake('local');

        $stocker = User::factory()->create(['api_token' => 'stocker-document-token']);
        $stocker->assignRole('Stocker');
        $this->createPurchasePresets();

        $response = $this->withToken('stocker-document-token')
            ->post('/api/tuntutan', $this->purchasePayload([
                'purchase_attachment' => UploadedFile::fake()->create('invoice.png', 100, 'image/png'),
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('documents.purchase_attachment.available', true)
            ->assertJsonPath('purchase_attachment_available', true);

        $claimId = $response->json('id');
        $claim = Tuntutan::query()->findOrFail($claimId);
        $this->assertNotNull($claim->purchase_attachment);
        $this->assertStringStartsWith('claim-documents/', $claim->purchase_attachment);
        Storage::disk('local')->assertExists($claim->purchase_attachment);

        $this->withToken('stocker-document-token')
            ->get("/api/tuntutan/{$claimId}/supporting-document")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_purchase_attachment_validation_rejects_disallowed_files(): void
    {
        $stocker = $this->stocker();
        $this->createPurchasePresets();

        $this->actingAs($stocker)
            ->post(route('tuntutan.store'), $this->purchasePayload([
                'purchase_attachment' => UploadedFile::fake()->create('not-allowed.exe', 100),
            ]))
            ->assertSessionHasErrors('purchase_attachment');
    }

    private function stocker(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Stocker');

        return $user;
    }

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Superadmin');

        return $user;
    }

    private function createPurchasePresets(): void
    {
        TuntutanPreset::create([
            'type' => TuntutanPreset::TYPE_PURCHASE_PLATFORM,
            'name' => 'Shopee',
            'sort_order' => 1,
        ]);
        TuntutanPreset::create([
            'type' => TuntutanPreset::TYPE_PAYMENT_METHOD,
            'name' => 'Bank Transfer',
            'sort_order' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(array $overrides = []): array
    {
        $today = now()->toDateString();

        return array_merge([
            'tag' => 'Pantry',
            'request_date' => $today,
            'item_specification' => '12 cartons of milk',
            'purchase_purpose' => 'Weekly pantry restock',
            'invoice_no' => 'QT-1001',
            'purchase_platform' => 'Shopee',
            'total_item_amount' => 45.90,
            'payment_method' => 'Bank Transfer',
            'invoice_sent_to_account' => 1,
            'date_receive' => $today,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function purchaseClaim(User $owner, array $overrides = []): Tuntutan
    {
        return Tuntutan::create(array_merge([
            'user_id' => $owner->id,
            'requestor_name' => $owner->name,
            'nama_item' => 'Purchase document test',
            'item_specification' => 'Purchase document test',
            'purchase_purpose' => 'Testing',
            'purchase_platform' => 'Shopee',
            'tag' => 'Pantry',
            'nilai_tuntutan' => 10.00,
            'total_item_amount' => 10.00,
            'payment_method' => 'Bank Transfer',
            'invoice_sent_to_account' => false,
            'tarikh_beli' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'date_receive' => now()->toDateString(),
            'minggu_tuntutan' => now()->format('o-\\WW'),
            'status' => 'Pending',
        ], $overrides));
    }
}
