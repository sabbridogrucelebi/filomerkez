<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class TelegramAlertSchedule
{
    public const TIME = '07:50';
    public const TIMEZONE = 'Europe/Istanbul';
    public const DEFAULT_DAYS = [1, 3, 5, 7, 10, 15, 20];

    public static function days(array $days): array
    {
        return collect($days)->map(fn ($day) => filter_var($day, FILTER_VALIDATE_INT))
            ->filter(fn ($day) => $day !== false && $day >= 0 && $day <= 365)
            ->unique()->sort()->values()->all();
    }

    public static function dueThreshold(array $sortedDays, int $remaining): ?int
    {
        foreach ($sortedDays as $day) {
            if ($day >= $remaining) {
                return $day;
            }
        }

        return null;
    }

    public static function policyThreshold(array $sortedDays, int $remaining): ?int
    {
        if ($remaining >= 0) {
            return self::dueThreshold($sortedDays, $remaining);
        }

        return in_array($remaining, [-1, -5], true) ? $remaining : null;
    }

    public static function parseDays(string $text): array
    {
        $days = self::days(preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));
        if ($days === []) {
            throw ValidationException::withMessages(['reminder_days_text' => '0 ile 365 arasında en az bir uyarı günü girin.']);
        }

        return $days;
    }
}
