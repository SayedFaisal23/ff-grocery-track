<?php

namespace App\Console\Commands;

use App\Models\Inventori;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramRestockAlert extends Command
{
    protected $signature = 'telegram:send-restock-alert';

    protected $description = 'Send Telegram reminders for out-of-stock and below-threshold items';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (empty($token) || empty($chatId)) {
            $this->warn('Telegram bot token or chat ID is not configured.');
            Log::warning('Telegram bot token or chat ID is not configured.');

            return self::FAILURE;
        }

        $items = Inventori::whereColumn('jumlah_belum_dibuka', '<=', 'had_ambang')
            ->orderBy('nama_item')
            ->get();

        if ($items->isEmpty()) {
            $this->info('No items have reached the restock threshold.');

            return self::SUCCESS;
        }

        $outOfStockItems = $items->where('jumlah_belum_dibuka', 0);
        $belowThresholdItems = $items->where('jumlah_belum_dibuka', '>', 0);
        $sections = [];

        if ($outOfStockItems->isNotEmpty()) {
            $sections[] = "HABIS STOK\n".$outOfStockItems
                ->map(fn (Inventori $item) => "• {$item->nama_item} — Baki: 0 unit")
                ->implode("\n");
        }

        if ($belowThresholdItems->isNotEmpty()) {
            $sections[] = "BAKI DI BAWAH HAD\n".$belowThresholdItems
                ->map(fn (Inventori $item) => "• {$item->nama_item} — Baki: {$item->jumlah_belum_dibuka} unit | Had: {$item->had_ambang} unit")
                ->implode("\n");
        }

        $message = "FFGrocery — Peringatan Restok\n\n"
            .implode("\n\n", $sections)
            ."\n\nSila beli item di atas dan semak sistem untuk maklumat lanjut.";

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            if ($response->failed()) {
                $this->error('Telegram API error: '.$response->body());
                Log::error('Telegram API error response: '.$response->body());

                return self::FAILURE;
            }

            $this->info('Telegram restock alert sent successfully.');
            Log::info("Telegram restock reminder sent for {$items->count()} items.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Failed to send Telegram notification: '.$exception->getMessage());
            Log::error('Failed to send Telegram notification: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
