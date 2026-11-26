<?php

namespace App\Console\Commands;

use App\Services\TelegramKaskoNotificationService;
use Illuminate\Console\Command;
use Throwable;

class SendTelegramKaskoAlerts extends Command
{
    protected $signature = 'telegram:kasko-alerts {--scheduled : Resume today’s automatic check}';

    protected $description = 'Sends configured Telegram alerts for vehicle kasko expiry dates.';

    public function handle(TelegramKaskoNotificationService $telegram): int
    {
        try {
            $sent = $telegram->sendScheduledNotifications((bool) $this->option('scheduled'));
            $this->info("Telegram kasko bildirimleri tamamlandı: {$sent} mesaj gönderildi.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Telegram kasko bildirimleri gönderilemedi: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
