<?php

namespace Tests\Feature;

use App\Models\Inventori;
use App\Models\Kategori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class TelegramRestockAlertTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.bot_token', 'test_bot_token');
        Config::set('services.telegram.chat_id', 'test_chat_id');

        $this->kategori = Kategori::create(['nama' => 'Ujian']);
    }

    public function test_command_sends_reminders_for_zero_and_below_threshold_items(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        Inventori::create([
            'nama_item' => 'Apple Milk',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 0,
            'peratus_baki' => 0,
            'had_ambang' => 2,
        ]);

        Inventori::create([
            'nama_item' => 'Gardenia Bread',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 1,
            'peratus_baki' => 80,
            'had_ambang' => 3,
        ]);

        Inventori::create([
            'nama_item' => 'Coca Cola',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 5,
            'peratus_baki' => 100,
            'had_ambang' => 2,
        ]);

        $this->artisan('telegram:send-restock-alert')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === 'test_chat_id'
                && str_contains($request['text'], 'FFGrocery — Peringatan Restok')
                && str_contains($request['text'], 'HABIS STOK')
                && str_contains($request['text'], 'Apple Milk — Baki: 0 unit')
                && str_contains($request['text'], 'BAKI DI BAWAH HAD')
                && str_contains($request['text'], 'Gardenia Bread — Baki: 1 unit | Had: 3 unit')
                && ! str_contains($request['text'], 'Coca Cola');
        });
    }

    public function test_command_does_not_send_when_every_item_is_above_threshold(): void
    {
        Http::fake();

        Inventori::create([
            'nama_item' => 'Coca Cola',
            'kategori_id' => $this->kategori->id,
            'jumlah_belum_dibuka' => 5,
            'peratus_baki' => 100,
            'had_ambang' => 2,
        ]);

        $this->artisan('telegram:send-restock-alert')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_command_fails_when_telegram_config_is_missing(): void
    {
        Config::set('services.telegram.bot_token', '');
        Config::set('services.telegram.chat_id', '');

        Http::fake();

        $this->artisan('telegram:send-restock-alert')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    public function test_command_is_scheduled_for_weekdays_at_6pm_malaysia_time(): void
    {
        $event = collect(Schedule::events())
            ->first(fn ($scheduledEvent) => str_contains($scheduledEvent->command, 'telegram:send-restock-alert'));

        $this->assertNotNull($event);
        $this->assertSame('0 18 * * 1-5', $event->expression);
        $this->assertSame('Asia/Kuala_Lumpur', $event->timezone);
    }
}
