<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class TelegramSendCooldown
{
    public static function remaining(?string $token): int
    {
        if (blank($token)) {
            return 0;
        }

        return max(0, (int) Cache::get(self::key($token), 0) - now()->getTimestamp());
    }

    public static function defer(string $token, int $seconds): int
    {
        $seconds = max(1, $seconds, self::remaining($token));
        Cache::put(self::key($token), now()->getTimestamp() + $seconds, $seconds);

        return $seconds;
    }

    private static function key(string $token): string
    {
        $bot = str($token)->before(':');

        return 'telegram:send:cooldown:' . hash('sha256', (string) $bot);
    }
}
