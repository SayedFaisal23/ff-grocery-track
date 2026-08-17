<?php

namespace Tests\Feature;

use App\Models\Tuntutan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseRequestSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Stocker']);
        Role::create(['name' => 'Tracker']);
    }

    public function test_hot_search_matches_partial_item_specifications_and_renders_an_accessible_debounced_control(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();
        $kitchenTowels = $this->purchaseRequest($stocker, 'Kitchen towel rolls');
        $paperTowels = $this->purchaseRequest($stocker, 'Paper towels');
        $legacyNameOnly = $this->purchaseRequest($stocker, 'Office paper', [
            'nama_item' => 'Towel legacy label',
        ]);

        $response = $this->actingAs($superadmin)->get(route('tuntutan.index', [
            'month' => '2026-08',
            'search' => 'towel',
        ]));
        $cards = $this->attentionCards($response);

        $response
            ->assertOk()
            ->assertSee('Search request name')
            ->assertSee('id="tuntutan-search"', false)
            ->assertSee('type="search"', false)
            ->assertSee('name="search"', false)
            ->assertSee('value="towel"', false)
            ->assertSee('maxlength="255"', false)
            ->assertSee('aria-label="Search purchase requests by Spesifikasi Item"', false)
            ->assertSee('data-claims-search-filter', false)
            ->assertSee('data-claims-search-input', false)
            ->assertSee('form.requestSubmit()', false)
            ->assertSee('}, 300)', false)
            ->assertDontSeeText('Towel legacy label');

        $this->assertSame('towel', $response->viewData('selectedSearch'));
        $this->assertEqualsCanonicalizing(
            [$kitchenTowels->id, $paperTowels->id],
            $this->visibleClaimIds($response),
        );
        $this->assertSame(2, $cards['To be approved/rejected']['count']);
        $this->assertSame([], collect($cards['Proof of Payment to be uploaded']['claims'])->all());
        $this->assertSame([], collect($cards['Invoice/Receipt to be reviewed']['claims'])->all());
        $this->assertNotContains($legacyNameOnly->id, $this->visibleClaimIds($response));
    }

    public function test_search_combines_with_existing_filters_and_keeps_search_in_filter_navigation(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();
        $matchingClaim = $this->purchaseRequest($stocker, 'Blue toner replacement', [
            'tag' => 'Pantry',
            'minggu_tuntutan' => '2026-W32',
        ]);
        $differentType = $this->purchaseRequest($stocker, 'Blue toner cartridge', [
            'tag' => 'General',
            'minggu_tuntutan' => '2026-W32',
        ]);
        $differentWeek = $this->purchaseRequest($stocker, 'Blue toner for archive printer', [
            'tag' => 'Pantry',
            'minggu_tuntutan' => '2026-W31',
        ]);
        $differentStatus = $this->purchaseRequest($stocker, 'Blue toner completed', [
            'tag' => 'Pantry',
            'minggu_tuntutan' => '2026-W32',
            'status' => 'Completed',
            'approval_result' => 'Approved',
        ]);

        $response = $this->actingAs($superadmin)->get(route('tuntutan.index', [
            'month' => '2026-08',
            'weeks' => ['2026-W32'],
            'type' => 'Pantry',
            'status' => 'submitted',
            'search' => 'Blue toner',
        ]));
        $cards = $this->attentionCards($response);

        $response
            ->assertOk()
            ->assertSee('value="Blue toner"', false)
            ->assertSee('search=Blue%20toner', false)
            ->assertSee('href="#claim-'.$matchingClaim->id.'"', false)
            ->assertDontSee('href="#claim-'.$differentType->id.'"', false)
            ->assertDontSee('href="#claim-'.$differentWeek->id.'"', false)
            ->assertDontSee('href="#claim-'.$differentStatus->id.'"', false);

        $this->assertMatchesRegularExpression(
            '#<input\\b(?=[^>]*\\bname="search")(?=[^>]*\\bvalue="Blue toner")[^>]*>#',
            $response->getContent(),
        );

        $this->assertSame([$matchingClaim->id], $this->visibleClaimIds($response));
        $this->assertSame(1, $cards['To be approved/rejected']['count']);
        $this->assertSame([$matchingClaim->id], collect($cards['To be approved/rejected']['claims'])->pluck('id')->all());
        $this->assertSame(0, $cards['Proof of Payment to be uploaded']['count']);
        $this->assertSame(0, $cards['Invoice/Receipt to be reviewed']['count']);
    }

    public function test_blank_search_is_ignored_and_unmatched_search_has_no_cards_or_claims(): void
    {
        $superadmin = $this->superadmin();
        $stocker = $this->stocker();
        $claim = $this->purchaseRequest($stocker, 'Coffee supplies');

        $blankResponse = $this->actingAs($superadmin)->get(route('tuntutan.index', [
            'month' => '2026-08',
            'search' => '   ',
        ]));

        $blankResponse
            ->assertOk()
            ->assertSee('value=""', false);
        $this->assertNull($blankResponse->viewData('selectedSearch'));
        $this->assertSame([$claim->id], $this->visibleClaimIds($blankResponse));

        $unmatchedResponse = $this->actingAs($superadmin)->get(route('tuntutan.index', [
            'month' => '2026-08',
            'search' => 'No matching request',
        ]));
        $cards = $this->attentionCards($unmatchedResponse);

        $unmatchedResponse
            ->assertOk()
            ->assertSee('value="No matching request"', false)
            ->assertSeeText('No purchase requests found.')
            ->assertDontSee('href="#claim-'.$claim->id.'"', false);
        $this->assertSame([], $this->visibleClaimIds($unmatchedResponse));
        $this->assertSame(0, $cards['To be approved/rejected']['count']);
        $this->assertSame(0, $cards['Proof of Payment to be uploaded']['count']);
        $this->assertSame(0, $cards['Invoice/Receipt to be reviewed']['count']);
    }

    public function test_stocker_search_cannot_return_or_count_another_stocker_request(): void
    {
        $stocker = $this->stocker();
        $otherStocker = $this->stocker();
        $ownRequest = $this->purchaseRequest($stocker, 'Office chair replacement', [
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ]);
        $otherRequest = $this->purchaseRequest($otherStocker, 'Office chair confidential request', [
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ]);
        $ownNonMatch = $this->purchaseRequest($stocker, 'Printer labels', [
            'approval_result' => 'Approved',
            'payment_workflow' => Tuntutan::PAYMENT_WORKFLOW_OWN_EXPENSES,
        ]);

        $response = $this->actingAs($stocker)->get(route('tuntutan.index', [
            'month' => '2026-08',
            'search' => 'Office chair',
        ]));
        $cards = $this->attentionCards($response);

        $response
            ->assertOk()
            ->assertSee('href="#claim-'.$ownRequest->id.'"', false)
            ->assertDontSee('href="#claim-'.$otherRequest->id.'"', false)
            ->assertDontSeeText('Office chair confidential request');

        $this->assertSame([$ownRequest->id], $this->visibleClaimIds($response));
        $this->assertSame(1, $cards['Invoice/Receipt to be uploaded']['count']);
        $this->assertSame([$ownRequest->id], collect($cards['Invoice/Receipt to be uploaded']['claims'])->pluck('id')->all());
        $this->assertSame(0, $cards['Proof of Payment to be reviewed']['count']);
        $this->assertNotContains($otherRequest->id, $this->visibleClaimIds($response));
        $this->assertNotContains($ownNonMatch->id, $this->visibleClaimIds($response));
    }

    private function attentionCards($response): Collection
    {
        return collect($response->viewData('attentionCards'))->keyBy('title');
    }

    private function visibleClaimIds($response): array
    {
        return $response->viewData('claimsGrouped')
            ->collapse()
            ->pluck('id')
            ->all();
    }

    private function purchaseRequest(User $owner, string $itemSpecification, array $overrides = []): Tuntutan
    {
        return Tuntutan::create(array_merge([
            'user_id' => $owner->id,
            'requestor_name' => $owner->name,
            'nama_item' => $itemSpecification,
            'item_specification' => $itemSpecification,
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

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Superadmin');

        return $user;
    }

    private function stocker(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Stocker');

        return $user;
    }
}
