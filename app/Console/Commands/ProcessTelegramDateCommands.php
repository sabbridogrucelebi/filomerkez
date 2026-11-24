<?php

namespace App\Console\Commands;

use App\Services\TelegramDateChatService;
use Illuminate\Console\Command;

class ProcessTelegramDateCommands extends Command
{
    protected $signature = 'telegram:date-bot-commands';

    protected $aliases = ['telegram:imm-commands'];

    protected $description = 'Replies to control requests in configured Telegram date-alert groups.';

    public function handle(TelegramDateChatService $chat): int
    {
        $handled = $chat->processIncomingCommands();
        $this->info("Telegram tarih grubu komutları: {$handled} kontrol işlendi.");

        return self::SUCCESS;
    }
}
