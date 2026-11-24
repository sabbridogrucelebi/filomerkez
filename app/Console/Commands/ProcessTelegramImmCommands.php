<?php

namespace App\Console\Commands;

use App\Services\TelegramImmChatService;
use Illuminate\Console\Command;

class ProcessTelegramImmCommands extends Command
{
    protected $signature = 'telegram:imm-commands';

    protected $description = 'Replies to control requests in configured Telegram İMM groups.';

    public function handle(TelegramImmChatService $chat): int
    {
        $handled = $chat->processIncomingCommands();
        $this->info("Telegram İMM grup komutları: {$handled} kontrol işlendi.");

        return self::SUCCESS;
    }
}
