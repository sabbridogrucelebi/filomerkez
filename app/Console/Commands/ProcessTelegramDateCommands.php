<?php

namespace App\Console\Commands;

use App\Services\TelegramDateChatService;
use Illuminate\Console\Command;

class ProcessTelegramDateCommands extends Command
{
    protected $signature = 'telegram:date-bot-commands';

    protected $aliases = ['telegram:imm-commands'];

    protected $description = 'Replies to fleet questions and control requests in configured Telegram groups.';

    public function handle(TelegramDateChatService $chat): int
    {
        $handled = $chat->processIncomingCommands();
        $this->info("Telegram grup komutları: {$handled} istek işlendi.");

        return self::SUCCESS;
    }
}
