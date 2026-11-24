<?php

namespace App\Exceptions;

use RuntimeException;

class TelegramRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Telegram gönderimi geçici olarak sınırladı. ' . $retryAfter . ' saniye sonra tekrar deneyin.');
    }
}
