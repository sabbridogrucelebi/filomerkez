<?php

namespace App\Services;

use RuntimeException;

final class TelegramWebhookAddress
{
    public static function forRoute(string $route, int $id): string
    {
        $base = rtrim(config('app.url'), '/');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https' || !parse_url($base, PHP_URL_HOST)) {
            if (request()->getHost() !== 'mehmetcelebiturizm.com') {
                throw new RuntimeException('Sunucuda APP_URL, Filo Merkez’in tam HTTPS adresi olmalı.');
            }
            $base = 'https://mehmetcelebiturizm.com/app';
        } elseif (!parse_url($base, PHP_URL_PATH)) {
            $base .= rtrim(request()->getBaseUrl(), '/');
        }

        return $base . route($route, $id, false);
    }
}
