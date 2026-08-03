<?php

namespace Tests\Feature;

use App\Models\Inventori;
use App\Models\Kategori;
use App\Models\LogAktiviti;
use App\Models\Tuntutan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FFGroceryTrackTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        // Cipta peranan (roles) asas
        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Stocker']);
        Role::create(['name' => 'Tracker']);

        $this->kategori = Kategori::create(['nama' => 'Tenusu']);
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Log Masuk');
    }

    public function test_category_color_defaults_to_current_indigo(): void
    {
        $this->assertSame(Kategori::DEFAULT_WARNA, $this->kategori->warna);
        $this->assertDatabaseHas('categories', [
            'id' => $this->kategori->id,
            'warna' => Kategori::DEFAULT_WARNA,
        ]);
    }

    public function test_superadmin_can_access_dashboard_and_logs(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $response = $this->actingAs($superadmin)->get('/inventori');
        $response->assertStatus(200);

        $responseLogs = $this->actingAs($superadmin)->get('/log-aktiviti');
        $responseLogs->assertStatus(200);
    }

    public function test_stocker_cannot_access_logs_but_can_access_tuntutan(): void
    {
        $stocker = User::factory()->create();
        $stocker->assignRole('Stocker');

        $responseLogs = $this->actingAs($stocker)->get('/log-aktiviti');
        $responseLogs->assertStatus(403);

        $responseClaims = $this->actingAs($stocker)->get('/tuntutan');
        $responseClaims->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/inventori');
        $response->assertRedirect('/login');
    }

    public function test_superadmin_can_create_another_superadmin(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $response = $this->actingAs($superadmin)->post('/pengguna', [
            'name' => 'New Superadmin',
            'email' => 'newadmin@email.com',
            'role' => 'Superadmin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/pengguna');
        $this->assertDatabaseHas('users', ['email' => 'newadmin@email.com']);
        $newAdmin = User::where('email', 'newadmin@email.com')->first();
        $this->assertTrue($newAdmin->hasRole('Superadmin'));
    }

    public function test_tracker_can_create_and_delete_items(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        // Test create item
        $responseCreate = $this->actingAs($tracker)->post('/inventori', [
            'nama_item' => 'Tracker Milk',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 5,
            'peratus_baki' => 100,
            'had_ambang' => 2,
        ]);
        $responseCreate->assertRedirect('/inventori');
        $this->assertDatabaseHas('inventori', ['nama_item' => 'Tracker Milk']);

        $item = Inventori::where('nama_item', 'Tracker Milk')->first();

        // Test delete item
        $responseDelete = $this->actingAs($tracker)->delete('/inventori/'.$item->id);
        $responseDelete->assertRedirect('/inventori');
        $this->assertDatabaseMissing('inventori', ['id' => $item->id]);
    }

    public function test_tracker_cannot_access_tuntutan(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        $response = $this->actingAs($tracker)->get('/tuntutan');
        $response->assertStatus(403);
    }

    public function test_stocker_can_submit_weekly_lunch_claim(): void
    {
        $stocker = User::factory()->create();
        $stocker->assignRole('Stocker');

        $response = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Lunch',
            'week' => '2026-W29',
            'lunch_dates' => [
                '2026-07-13',
                '2026-07-14',
                '2026-07-15',
                '2026-07-16',
                '2026-07-17',
                '2026-07-18',
                '2026-07-19',
            ],
            'lunch_butirans' => [
                'Lunch Mon',
                'Lunch Tue',
                'Lunch Claim',
                'Lunch Claim',
                'Lunch Claim',
                'Lunch Claim',
                'Lunch Claim',
            ],
            'lunch_pax' => [
                10,
                12,
                0,
                0,
                0,
                0,
                0,
            ],
            'lunch_hargas' => [
                12.50,
                10.00, // Different price on Tuesday
                0,
                0,
                0,
                0,
                0,
            ],
        ]);

        $response->assertRedirect('/tuntutan');

        // Check if database contains the claims for Mon and Tue, but not other days
        $this->assertDatabaseHas('tuntutan', [
            'user_id' => $stocker->id,
            'tag' => 'Lunch',
            'nilai_tuntutan' => 125.00,
            'tarikh_beli' => '2026-07-13 00:00:00',
            'minggu_tuntutan' => '2026-W29',
            'nama_item' => 'Lunch Mon (10 pax @ RM 12.50/pax)',
        ]);

        $this->assertDatabaseHas('tuntutan', [
            'user_id' => $stocker->id,
            'tag' => 'Lunch',
            'nilai_tuntutan' => 120.00, // 12 * 10.00
            'tarikh_beli' => '2026-07-14 00:00:00',
            'minggu_tuntutan' => '2026-W29',
            'nama_item' => 'Lunch Tue (12 pax @ RM 10.00/pax)',
        ]);

        // There should be exactly 2 claims created
        $this->assertEquals(2, Tuntutan::where('user_id', $stocker->id)->count());
    }

    public function test_stocker_cannot_submit_invalid_lunch_claims(): void
    {
        $stocker = User::factory()->create();
        $stocker->assignRole('Stocker');

        // 1. Submit with zero total pax
        $response = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Lunch',
            'week' => '2026-W29',
            'lunch_dates' => [
                '2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
            ],
            'lunch_butirans' => [
                'Lunch Mon', 'Lunch Tue', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim',
            ],
            'lunch_pax' => [
                0, 0, 0, 0, 0, 0, 0,
            ],
            'lunch_hargas' => [
                12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.50,
            ],
        ]);
        $response->assertSessionHasErrors(['lunch_pax']);

        // 2. Submit with future date
        $futureDate = Carbon::now()->addYear()->format('Y-m-d');
        $response2 = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Lunch',
            'week' => '2026-W29',
            'lunch_dates' => [
                $futureDate, '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
            ],
            'lunch_butirans' => [
                'Lunch Mon', 'Lunch Tue', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim',
            ],
            'lunch_pax' => [
                5, 0, 0, 0, 0, 0, 0,
            ],
            'lunch_hargas' => [
                12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.50,
            ],
        ]);
        $response2->assertSessionHasErrors(['lunch_dates']);

        // 3. Submit with missing butiran for claimed day
        $response3 = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Lunch',
            'week' => '2026-W29',
            'lunch_dates' => [
                '2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
            ],
            'lunch_butirans' => [
                '', 'Lunch Tue', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim',
            ],
            'lunch_pax' => [
                5, 0, 0, 0, 0, 0, 0,
            ],
            'lunch_hargas' => [
                12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.50,
            ],
        ]);
        $response3->assertSessionHasErrors(['lunch_butirans']);

        // 4. Submit with missing/zero price for claimed day
        $response4 = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Lunch',
            'week' => '2026-W29',
            'lunch_dates' => [
                '2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
            ],
            'lunch_butirans' => [
                'Lunch Mon', 'Lunch Tue', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim', 'Lunch Claim',
            ],
            'lunch_pax' => [
                5, 0, 0, 0, 0, 0, 0,
            ],
            'lunch_hargas' => [
                0, 0, 0, 0, 0, 0, 0,
            ],
        ]);
        $response4->assertSessionHasErrors(['lunch_hargas']);
    }

    public function test_stocker_can_submit_general_and_food_claim_with_attachment(): void
    {
        Storage::fake('public');

        $stocker = User::factory()->create();
        $stocker->assignRole('Stocker');

        $file = UploadedFile::fake()->create('receipt.pdf', 100); // 100kb PDF

        // 1. Test General claim
        $responseGeneral = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'General',
            'nama_item' => 'Barang Pejabat A4 Paper',
            'nilai_tuntutan' => 45.90,
            'tarikh_beli' => '2026-07-20',
            'attachment' => $file,
        ]);

        $responseGeneral->assertRedirect('/tuntutan');
        $this->assertDatabaseHas('tuntutan', [
            'user_id' => $stocker->id,
            'tag' => 'General',
            'nilai_tuntutan' => 45.90,
            'nama_item' => 'Barang Pejabat A4 Paper',
        ]);

        $claimGeneral = Tuntutan::where('tag', 'General')->first();
        $this->assertNotNull($claimGeneral->attachment);
        Storage::disk('public')->assertExists($claimGeneral->attachment);

        // 2. Test Food claim
        $file2 = UploadedFile::fake()->create('food_receipt.png', 200); // 200kb Image
        $responseFood = $this->actingAs($stocker)->post('/tuntutan', [
            'tag' => 'Food',
            'nama_item' => 'Katering Makan Malam',
            'nilai_tuntutan' => 150.00,
            'tarikh_beli' => '2026-07-20',
            'attachment' => $file2,
        ]);

        $responseFood->assertRedirect('/tuntutan');
        $this->assertDatabaseHas('tuntutan', [
            'user_id' => $stocker->id,
            'tag' => 'Food',
            'nilai_tuntutan' => 150.00,
            'nama_item' => 'Katering Makan Malam',
        ]);

        $claimFood = Tuntutan::where('tag', 'Food')->first();
        $this->assertNotNull($claimFood->attachment);
        Storage::disk('public')->assertExists($claimFood->attachment);
    }

    public function test_claim_attachment_is_available_to_its_owner_and_superadmin_only(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $owner->assignRole('Stocker');

        $otherStocker = User::factory()->create();
        $otherStocker->assignRole('Stocker');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $attachmentPath = 'attachments/receipt.pdf';
        Storage::disk('public')->put($attachmentPath, 'receipt content');

        $claim = Tuntutan::create([
            'user_id' => $owner->id,
            'nama_item' => 'Barang Ujian',
            'tag' => 'Stok',
            'nilai_tuntutan' => 10.00,
            'tarikh_beli' => '2026-07-20',
            'minggu_tuntutan' => '2026-W30',
            'status' => 'Dalam Proses',
            'attachment' => $attachmentPath,
        ]);

        $this->actingAs($owner)
            ->get(route('tuntutan.attachment', $claim))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($superadmin)
            ->get(route('tuntutan.attachment', $claim))
            ->assertOk();

        $this->actingAs($otherStocker)
            ->get(route('tuntutan.attachment', $claim))
            ->assertForbidden();
    }

    public function test_superadmin_can_manage_category_presets(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin)
            ->get('/kategori')
            ->assertOk()
            ->assertSee('Pengurusan Kategori')
            ->assertSee('Warna')
            ->assertSee('type="color"', false)
            ->assertSee('Simpan')
            ->assertDontSee('Simpan Semua')
            ->assertDontSee('Simpan kategori');

        $this->actingAs($superadmin)
            ->post('/kategori', [
                'nama' => 'Minuman',
                'warna' => '#F97316',
            ])
            ->assertRedirect('/kategori');

        $category = Kategori::where('nama', 'Minuman')->firstOrFail();

        $this->actingAs($superadmin)
            ->put('/kategori', [
                'categories' => [
                    $this->kategori->id => [
                        'nama' => 'Produk Tenusu',
                        'warna' => '#0EA5E9',
                    ],
                    $category->id => [
                        'nama' => 'Minuman Sejuk',
                        'warna' => '#F59E0B',
                    ],
                ],
            ])
            ->assertRedirect('/kategori');

        $this->assertDatabaseHas('categories', ['nama' => 'Produk Tenusu', 'warna' => '#0EA5E9']);
        $this->assertDatabaseHas('categories', ['nama' => 'Minuman Sejuk', 'warna' => '#F59E0B']);

        $this->actingAs($superadmin)
            ->delete("/kategori/{$category->id}")
            ->assertRedirect('/kategori');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_bulk_category_update_is_atomic_when_names_are_duplicated(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');
        $category = Kategori::create(['nama' => 'Minuman']);

        $this->actingAs($superadmin)
            ->from('/kategori')
            ->put('/kategori', [
                'categories' => [
                    $this->kategori->id => [
                        'nama' => 'Nama Sama',
                        'warna' => '#0EA5E9',
                    ],
                    $category->id => [
                        'nama' => 'Nama Sama',
                        'warna' => '#F59E0B',
                    ],
                ],
            ])
            ->assertRedirect('/kategori')
            ->assertSessionHasErrors('categories');

        $this->assertDatabaseHas('categories', ['id' => $this->kategori->id, 'nama' => 'Tenusu']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'nama' => 'Minuman']);
    }

    public function test_bulk_category_update_accepts_unique_names_when_another_category_is_unchanged(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');
        $category = Kategori::create(['nama' => 'Minuman']);

        $this->actingAs($superadmin)
            ->put('/kategori', [
                'categories' => [
                    $this->kategori->id => [
                        'nama' => 'Produk Tenusu',
                        'warna' => '#0EA5E9',
                    ],
                    $category->id => [
                        'nama' => 'Minuman',
                        'warna' => '#F59E0B',
                    ],
                ],
            ])
            ->assertRedirect('/kategori')
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('categories', ['id' => $this->kategori->id, 'nama' => 'Produk Tenusu']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'nama' => 'Minuman']);
    }

    public function test_bulk_category_color_update_is_logged_and_invalid_colors_are_rejected(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $this->actingAs($superadmin)
            ->put('/kategori', [
                'categories' => [
                    $this->kategori->id => [
                        'nama' => 'Tenusu',
                        'warna' => '#0ea5e9',
                    ],
                ],
            ])
            ->assertRedirect('/kategori')
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $this->kategori->id,
            'nama' => 'Tenusu',
            'warna' => '#0EA5E9',
        ]);

        $log = LogAktiviti::latest('id')->firstOrFail();
        $this->assertSame('#0EA5E9', $log->data_baru[0]['warna']);

        $this->actingAs($superadmin)
            ->from('/kategori')
            ->post('/kategori', [
                'nama' => 'Kategori Tidak Sah',
                'warna' => 'blue',
            ])
            ->assertRedirect('/kategori')
            ->assertSessionHasErrors('warna');

        $this->assertDatabaseMissing('categories', ['nama' => 'Kategori Tidak Sah']);
    }

    public function test_category_in_use_shows_a_clear_deletion_explanation(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        Inventori::create([
            'nama_item' => 'Susu Ujian',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 1,
            'peratus_baki' => 100,
            'had_ambang' => 1,
        ]);

        $this->actingAs($superadmin)
            ->get('/kategori')
            ->assertOk()
            ->assertSee('Tidak boleh dipadam: digunakan oleh item inventori')
            ->assertDontSee('title="Padam kategori"', false);
    }

    public function test_application_uses_malaysia_time(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', config('app.timezone'));
    }

    public function test_non_admin_cannot_manage_category_presets(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        $this->actingAs($tracker)
            ->get('/kategori')
            ->assertForbidden();

        $this->actingAs($tracker)
            ->put('/kategori', [
                'categories' => [
                    $this->kategori->id => [
                        'nama' => 'Tidak Dibenarkan',
                        'warna' => '#0EA5E9',
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_inventori_and_restock_show_the_selected_category_pill_color(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');
        $this->kategori->update(['warna' => '#0EA5E9']);

        Inventori::create([
            'nama_item' => 'Susu Ujian',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 0,
            'peratus_baki' => 100,
            'had_ambang' => 1,
        ]);

        $this->actingAs($tracker)
            ->get('/inventori')
            ->assertOk()
            ->assertSee('background-color: #0EA5E926', false)
            ->assertSee('color: #0EA5E9', false);

        $this->actingAs($tracker)
            ->get('/restok')
            ->assertOk()
            ->assertSee('background-color: #0EA5E926', false)
            ->assertSee('color: #0EA5E9', false);
    }

    public function test_mobile_inventory_cards_open_the_adjustment_sheet_without_expanding(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        Inventori::create([
            'nama_item' => 'Susu Ujian',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 2,
            'peratus_baki' => 100,
            'had_ambang' => 1,
        ]);

        $this->actingAs($tracker)
            ->get('/inventori')
            ->assertOk()
            ->assertSee('mobile-item-card-trigger', false)
            ->assertSee('fa-pen-to-square', false)
            ->assertSee('role="button"', false)
            ->assertSee('aria-controls="modalPelarasan"', false)
            ->assertSee('id="modalEditLink"', false)
            ->assertSee('id="modalDeleteForm"', false)
            ->assertSee('adjustment-modal-card', false)
            ->assertDontSee('toggleMobileCard', false)
            ->assertDontSee('mobile-card-body', false)
            ->assertDontSee('mobile-card-actions', false);
    }

    public function test_inventory_overview_counts_stock_and_expired_items(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        Carbon::setTestNow('2026-07-30 12:00:00');

        try {
            foreach ([
                ['nama_item' => 'Susu Segar', 'jumlah_belum_dibuka' => 4, 'had_ambang' => 1, 'tarikh_luput' => '2026-08-02', 'jejak_luput' => true],
                ['nama_item' => 'Beras', 'jumlah_belum_dibuka' => 2, 'had_ambang' => 2, 'tarikh_luput' => '2026-08-06', 'jejak_luput' => true],
                ['nama_item' => 'Biskut', 'jumlah_belum_dibuka' => 0, 'had_ambang' => 1, 'tarikh_luput' => null, 'jejak_luput' => false],
                ['nama_item' => 'Yogurt', 'jumlah_belum_dibuka' => 5, 'had_ambang' => 1, 'tarikh_luput' => '2026-08-06', 'jejak_luput' => true],
                ['nama_item' => 'Kopi', 'jumlah_belum_dibuka' => 10, 'had_ambang' => 2, 'tarikh_luput' => '2026-08-07', 'jejak_luput' => true],
                ['nama_item' => 'Tepung', 'jumlah_belum_dibuka' => 5, 'had_ambang' => 1, 'tarikh_luput' => '2026-07-29', 'jejak_luput' => true],
                ['nama_item' => 'Jus', 'jumlah_belum_dibuka' => 4, 'had_ambang' => 1, 'tarikh_luput' => '2026-08-01', 'jejak_luput' => false],
            ] as $attributes) {
                Inventori::create([
                    ...$attributes,
                    'kategori_id' => $this->kategori->id,
                    'peratus_baki' => 100,
                ]);
            }

            $response = $this->actingAs($tracker)->get('/inventori')->assertOk();

            $this->assertSame([
                'totalItems' => 7,
                'totalUnits' => 30,
                'outOfStock' => 1,
                'belowThreshold' => 1,
                'expired' => 1,
            ], $response->viewData('inventorySummary'));
            $response->assertSee('Ringkasan Inventori');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_inventory_expiry_date_uses_dd_mm_yyyy_format(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');
        $item = Inventori::create([
            'nama_item' => 'Teh Uncang Lipton',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 0,
            'peratus_baki' => 100,
            'had_ambang' => 1,
            'tarikh_luput' => '2026-07-31',
            'jejak_luput' => true,
        ]);

        $this->actingAs($tracker)
            ->get('/inventori/create')
            ->assertOk()
            ->assertSee('placeholder="dd/mm/yyyy"', false)
            ->assertSee('aria-label="Buka kalendar tarikh luput"', false);

        $this->actingAs($tracker)
            ->get('/inventori/'.$item->id.'/edit')
            ->assertOk()
            ->assertSee('value="31/07/2026"', false);

        $this->actingAs($tracker)
            ->put('/inventori/'.$item->id, [
                'nama_item' => 'Teh Uncang Lipton',
                'kategori_id' => $this->kategori->id,
                'jumlah_belum_dibuka' => 0,
                'peratus_baki' => 100,
                'had_ambang' => 1,
                'tarikh_luput' => '01/08/2026',
                'jejak_luput' => '1',
            ])
            ->assertRedirect('/inventori');

        $this->assertDatabaseHas('inventori', [
            'id' => $item->id,
            'tarikh_luput' => '2026-08-01',
        ]);
    }

    public function test_inventori_index_supports_single_column_sorting(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');
        $minuman = Kategori::create(['nama' => 'Minuman']);

        foreach ([
            ['nama_item' => 'Zeta', 'kategori_id' => $this->kategori->id, 'jumlah_belum_dibuka' => 5, 'tarikh_luput' => '2026-08-10', 'jejak_luput' => true],
            ['nama_item' => 'Alpha', 'kategori_id' => $minuman->id, 'jumlah_belum_dibuka' => 1, 'tarikh_luput' => '2026-08-05', 'jejak_luput' => true],
            ['nama_item' => 'Beta', 'kategori_id' => $minuman->id, 'jumlah_belum_dibuka' => 9, 'tarikh_luput' => null, 'jejak_luput' => false],
            ['nama_item' => 'Gamma', 'kategori_id' => $this->kategori->id, 'jumlah_belum_dibuka' => 5, 'tarikh_luput' => '2026-08-20', 'jejak_luput' => true],
        ] as $attributes) {
            Inventori::create([
                ...$attributes,
                'peratus_baki' => 100,
                'had_ambang' => 1,
            ]);
        }

        $assertOrder = function (?string $sort, array $expected) use ($tracker): void {
            $response = $this->actingAs($tracker)
                ->get('/inventori'.($sort ? '?sort='.$sort : ''))
                ->assertOk();

            $this->assertSame($expected, $response->viewData('items')->pluck('nama_item')->all());
        };

        $assertOrder(null, ['Alpha', 'Beta', 'Gamma', 'Zeta']);
        $assertOrder('invalid', ['Alpha', 'Beta', 'Gamma', 'Zeta']);
        $assertOrder('nama_asc', ['Alpha', 'Beta', 'Gamma', 'Zeta']);
        $assertOrder('nama_desc', ['Zeta', 'Gamma', 'Beta', 'Alpha']);
        $assertOrder('kategori_asc', ['Alpha', 'Beta', 'Gamma', 'Zeta']);
        $assertOrder('kategori_desc', ['Gamma', 'Zeta', 'Alpha', 'Beta']);
        $assertOrder('baki_asc', ['Alpha', 'Gamma', 'Zeta', 'Beta']);
        $assertOrder('baki_desc', ['Beta', 'Gamma', 'Zeta', 'Alpha']);
        $assertOrder('tarikh_luput_asc', ['Alpha', 'Zeta', 'Gamma', 'Beta']);
        $assertOrder('tarikh_luput_desc', ['Gamma', 'Zeta', 'Alpha', 'Beta']);
    }

    public function test_inventori_sort_controls_replace_the_previous_sort_and_preserve_filters(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        $response = $this->actingAs($tracker)
            ->get('/inventori?carian=Susu&kategori='.$this->kategori->id.'&sort=baki_asc')
            ->assertOk()
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('name="sort"', false)
            ->assertSee('value="baki_asc"', false)
            ->assertDontSee('id="inventoriSort"', false)
            ->assertSee('Set Semula');

        $response->assertSee(
            'carian=Susu&amp;kategori='.$this->kategori->id.'&amp;sort=kategori_asc',
            false
        );
    }

    public function test_inventori_crud_uses_preset_category_and_name_includes_brand(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        $responseCreate = $this->actingAs($tracker)->post('/inventori', [
            'nama_item' => 'Susu Tepung Fernleaf',
            'kategori_id' => $this->kategori->id,
            'jenis' => 'Serbuk',
            'capacity' => '1.8kg',
            'jumlah_belum_dibuka' => 8,
            'peratus_baki' => 80,
            'had_ambang' => 3,
        ]);

        $responseCreate->assertRedirect('/inventori');
        $this->assertDatabaseHas('inventori', [
            'nama_item' => 'Susu Tepung Fernleaf',
            'kategori_id' => $this->kategori->id,
            'jenis' => 'Serbuk',
            'capacity' => '1.8kg',
        ]);

        $item = Inventori::firstOrFail();

        $this->actingAs($tracker)
            ->get('/inventori')
            ->assertOk()
            ->assertSeeTextInOrder(['Serbuk', '•', '1.8kg'])
            ->assertDontSee('Varian:')
            ->assertDontSee('Kapasiti:');

        $responseUpdate = $this->actingAs($tracker)->put('/inventori/'.$item->id, [
            'nama_item' => 'Susu Tepung Fernleaf Gold',
            'kategori_id' => $this->kategori->id,
            'jenis' => 'Serbuk',
            'capacity' => '2kg',
            'jumlah_belum_dibuka' => 9,
            'peratus_baki' => 75,
            'had_ambang' => 4,
        ]);

        $responseUpdate->assertRedirect('/inventori');
        $this->assertDatabaseHas('inventori', [
            'id' => $item->id,
            'nama_item' => 'Susu Tepung Fernleaf Gold',
            'jenis' => 'Serbuk',
            'capacity' => '2kg',
        ]);
    }

    public function test_item_form_uses_category_dropdown_without_removed_fields(): void
    {
        $tracker = User::factory()->create();
        $tracker->assignRole('Tracker');

        $this->actingAs($tracker)
            ->get('/inventori/create')
            ->assertOk()
            ->assertSee('Nama/Jenama')
            ->assertSee('name="kategori_id"', false)
            ->assertSee('Tenusu')
            ->assertDontSee('name="kategori"', false)
            ->assertDontSee('name="jenama"', false)
            ->assertDontSee('jumlah_keseluruhan')
            ->assertDontSee('checked', false);
    }

    public function test_api_exposes_presets_and_accepts_an_existing_category_name(): void
    {
        $tracker = User::factory()->create(['api_token' => 'test-api-token']);
        $tracker->assignRole('Tracker');
        $headers = ['Authorization' => 'Bearer test-api-token'];

        $this->withHeaders($headers)
            ->getJson('/api/kategori')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->kategori->id,
                'nama' => 'Tenusu',
                'warna' => Kategori::DEFAULT_WARNA,
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/inventori', [
                'nama_item' => 'Susu Farm Fresh',
                'kategori' => 'Tenusu',
                'jenis' => 'Segar',
                'capacity' => '1L',
                'jumlah_belum_dibuka' => 2,
                'peratus_baki' => 100,
                'had_ambang' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('kategori', 'Tenusu');

        $this->assertDatabaseHas('inventori', [
            'nama_item' => 'Susu Farm Fresh',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 2,
        ]);
    }
}
