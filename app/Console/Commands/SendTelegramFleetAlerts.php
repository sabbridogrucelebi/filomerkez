<?php

namespace App\Console\Commands;

use App\Services\TelegramFleetAlertService;
use Illuminate\Console\Command;

class SendTelegramFleetAlerts extends Command
{
    protected $signature = 'telegram:fleet-alerts {--scheduled : Resume today’s automatic check}';
    protected $description = 'Send Telegram alerts for configured fleet channels.';

    public function handle(TelegramFleetAlertService $alerts): int
    {
        $sent = $alerts->sendScheduledNotifications((bool) $this->option('scheduled'));
        $this->info("Telegram filo bildirimleri: {$sent} mesaj gönderildi.");

        return self::SUCCESS;
    }
}
