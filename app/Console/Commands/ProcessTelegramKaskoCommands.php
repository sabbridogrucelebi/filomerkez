<?php

namespace App\Console\Commands;

use App\Services\TelegramKaskoChatService;
use Illuminate\Console\Command;
use Throwable;

class ProcessTelegramKaskoCommands extends Command
{
    protected $signature = 'telegram:kasko-commands';

    protected $description = 'Processes supported Telegram kasko chat commands.';

    public function handle(TelegramKaskoChatService $telegram): int
    {
        try {
            $handled = $telegram->processIncomingCommands();
            $this->info("Telegram kasko grup komutları tamamlandı: {$handled} komut işlendi.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Telegram kasko grup komutları işlenemedi: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
