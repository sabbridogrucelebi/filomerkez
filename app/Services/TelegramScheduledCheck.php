<?php

namespace App\Services;

use App\Exceptions\TelegramConnectionProblem;
use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramAlertBot;
use App\Models\TelegramKaskoNotificationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Tracks the automatic morning check independently for each company's bot. */
final class TelegramScheduledCheck
{
    private ?string $error = null;
    private bool $retry = false;
    private bool $needsReview = false;
    private bool $pause = false;
    public int $sent = 0;

    public static function status(int $company, string $channel): ?array
    {
        return Cache::get("telegram:auto:{$company}:{$channel}");
    }

    public static function schedulerNote(): string
    {
        $last = Cache::get('telegram:scheduler:last-tick');
        if (!$last) {
            return 'Zamanlayıcıdan henüz çalışma kaydı alınmadı.';
        }
        $time = \Carbon\Carbon::createFromTimestamp($last, TelegramAlertSchedule::TIMEZONE)->format('d.m.Y H:i');

        return now()->timestamp - $last > 300
            ? "Zamanlayıcı 5 dakikadan uzun süredir çalışmadı. Son kayıt · {$time}"
            : "Son zamanlayıcı çalışması · {$time}";
    }

    public static function run(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, bool $scheduled, callable $work): int
    {
        $channel = $bot instanceof TelegramAlertBot ? $bot->channel : 'kasko';
        $key = "telegram:auto:{$bot->company_id}:{$channel}";

        return (int) Cache::lock($key . ':lock', 3600)->get(function () use ($scheduled, $work, $key, $bot, $channel) {
            $now = now(TelegramAlertSchedule::TIMEZONE);
            $previous = self::status($bot->company_id, $channel);
            $sameDay = ($previous['day'] ?? '') === $now->toDateString();
            if ($scheduled && $sameDay && ($previous['version'] ?? null) === $bot->updated_at?->timestamp
                && in_array($previous['state'], ['done', 'review'], true)) {
                return 0;
            }

            $check = new self();
            try {
                if (!$bot->hasCredentials()) {
                    $check->error = 'Bot anahtarı veya sohbet kimliği eksik.';
                } elseif (($wait = TelegramSendCooldown::remaining($bot->bot_token)) > 0) {
                    throw new TelegramRateLimited($wait);
                } else {
                    $work($check);
                }
            } catch (Throwable $error) {
                $check->failed($error);
            }
            if ($check->error !== null) {
                Log::warning('Telegram otomatik kontrolünde gönderim tamamlanamadı.', [
                    'company_id' => $bot->company_id, 'channel' => $channel, 'reason' => $check->error,
                ]);
            }
            if ($scheduled) {
                $sent = $check->sent + ($sameDay ? $previous['sent'] : 0);
                $state = $check->error === null ? 'done' : ($check->retry && !$check->needsReview ? 'retry' : 'review');
                $summary = $check->error ?? ($sent > 0 ? 'Gönderim tamamlandı.' : 'Gönderilecek yeni uyarı bulunmadı.');
                Cache::put($key, ['day' => $now->toDateString(), 'state' => $state, 'sent' => $sent,
                    'checked_at' => $now->format('d.m.Y H:i'), 'summary' => $summary,
                    'version' => $bot->updated_at?->timestamp], $now->copy()->addWeek());
            }

            return $check->sent;
        });
    }

    public function failed(Throwable $error): void
    {
        if ($error instanceof TelegramRateLimited || ($error instanceof TelegramConnectionProblem && $error->beforeSend)) {
            $this->retry = true;
            $this->pause = true;
            if (!$this->needsReview) {
                $this->error = $error instanceof TelegramRateLimited ? 'Telegram hız sınırı uyguladı. Yeniden denenecek.'
                    : 'Telegram bağlantısı kurulamadı. Yeniden denenecek.';
            }
            return;
        }
        $this->needsReview = true;
        $this->pause = $this->pause || $error instanceof TelegramConnectionProblem || in_array($error->getCode(), [401, 403], true);
        $this->error = match (true) {
            $error instanceof TelegramConnectionProblem => 'Gönderimin sonucu doğrulanamadı. Telegram grubunu kontrol edin.',
            $error->getCode() === 401 => 'Telegram bot anahtarını kabul etmedi.',
            $error->getCode() === 403 => 'Telegram gönderim izni vermedi. Botun grup üyeliğini ve izinlerini kontrol edin.',
            $error->getCode() === 400 => 'Telegram isteği reddetti. Grup kimliğini ve mesaj şablonunu kontrol edin.',
            default => 'Kontrol tamamlanamadı. Bot ayarlarını ve sunucu günlüğünü kontrol edin.',
        };
        Log::warning('Telegram otomatik kontrol hatası.', ['error_type' => $error::class]);
    }

    public function shouldPause(): bool
    {
        return $this->pause;
    }
}
