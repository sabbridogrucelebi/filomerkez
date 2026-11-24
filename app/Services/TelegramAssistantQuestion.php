<?php

namespace App\Services;

use App\Models\TelegramAlertBot;
use Illuminate\Support\Str;

final class TelegramAssistantQuestion
{
    private const PLATE = '/(?<![A-Z0-9])(0[1-9]|[1-7][0-9]|8[01])\s*([A-Z]{1,3})\s*([0-9]{2,4})(?![A-Z0-9])/iu';
    private const NUMBERS = ['sifir' => 0, 'bir' => 1, 'iki' => 2, 'uc' => 3, 'dort' => 4, 'bes' => 5,
        'alti' => 6, 'yedi' => 7, 'sekiz' => 8, 'dokuz' => 9, 'on' => 10, 'yirmi' => 20, 'otuz' => 30,
        'kirk' => 40, 'elli' => 50, 'altmis' => 60, 'yetmis' => 70, 'seksen' => 80, 'doksan' => 90, 'yuz' => 100,
        'bin' => 1000];

    public function __construct(private readonly TelegramAssistantModel $model)
    {
    }

    public static function kind(string $channel): string
    {
        return $channel === 'kasko' ? 'date' : TelegramAlertBot::CHANNELS[$channel]['kind'];
    }

    public static function examples(string $channel): array
    {
        return match (self::kind($channel)) {
            'date' => ['Son 5 günü kalan araçları listele', 'Bitişi en yakın araç hangisi?', 'Süresi geçenler hangileri?'],
            'maintenance' => ['Bakıma 500 km kalan araçlar', 'Bakıma en yakın araç hangisi?', 'Bakımı gecikenleri listele'],
            'fuel' => ['Son 7 günde aşırı yakıt tüketen araçlar', 'En çok yakıt tüketen araç hangisi?', '42 ABC 123 yakıt durumu'],
        };
    }

    public static function intents(string $kind): array
    {
        return match ($kind) {
            'date' => ['upcoming', 'tomorrow', 'nearest', 'expired', 'missing', 'valid', 'vehicle', 'help'],
            'maintenance' => ['upcoming', 'nearest', 'expired', 'vehicle', 'help'],
            'fuel' => ['high', 'highest', 'vehicle', 'help'],
        };
    }

    public function parse(string $channel, string $original, bool $directed, ?array $previous = null): ?array
    {
        $kind = self::kind($channel);
        $text = Str::squish(Str::lower(Str::ascii($original, 'tr')));
        if ($text === '') {
            return $directed ? ['intent' => 'help'] : null;
        }
        $topic = $this->explicitTopic($text);
        $request = preg_match('/\b(liste\w*|hang\w*|arac\w*|goster\w*|cikar\w*|kalan\w*|bit\w*|suresi|gecik\w*|yaklasti\w*|as\w*tuket\w*|tuket\w*|kac|ne zaman|ne var|durum\w*|limit\w*|plan\w*|bugun|yarin)\b/u', $text);
        $plate = $this->plate($text);
        $followUp = $previous && preg_match('/^(?:peki\s+)?(\d+)(?:\s+(?:gun|km))?\s*\??$/', $text, $follow);
        $relevant = $directed || $plate || $followUp || $text === 'yardim' || $request || str_contains($text, 'en yakin');
        if (!$relevant) {
            return null;
        }
        if (preg_match('/\b(sil|iptal et|guncelle|degistir|sifre|anahtar|token|tc kimlik|vergi numarasi|sql)\b/', $text)) {
            return $directed ? ['intent' => 'help'] : null;
        }
        if ($topic && $topic !== $channel) {
            return $directed ? ['intent' => 'topic'] : null;
        }
        if ($text === 'yardim') {
            return ['intent' => 'help'];
        }
        if ($followUp && in_array($previous['intent'], ['upcoming', 'expired', 'high', 'highest'], true)) {
            return $this->validated($kind, ['intent' => $previous['intent'], 'amount' => (int) $follow[1], 'count' => $previous['count'] ?? false]);
        }

        $amount = $this->amount($text, $kind === 'maintenance' ? 'km|kilometre' : 'gun\w*|hafta\w*');
        $count = (bool) preg_match('/\b(sayisi|kac\s+(?:arac|adet|tane|police)|toplam kac)\b/', $text);
        if ($plate) {
            return $this->validated($kind, ['intent' => 'vehicle', 'amount' => $amount, 'plate' => $plate]);
        }
        $nearest = (bool) preg_match('/\b(en yakin|ilk bitecek|en erken|en az(?:\s+\w+){0,2}\s*kalan)\b/', $text);
        $expired = (bool) preg_match('/\b(gecik\w*|suresi (?:gec\w*|bit\w*|dolmus\w*)|bitmis\w*|sona ermis\w*|biteli)\b/', $text);
        $missing = (bool) preg_match('/\b(tanimsiz\w*|belgesiz\w*|belgesi olmayan\w*|tarihi olmayan\w*|kaydi olmayan\w*|(?:kaskosu|sigortasi|vizesi|muayenesi|policesi) olmayan\w*)\b/', $text);
        $upcoming = (bool) preg_match('/\b(kalan\w*|yaklas\w*|yakla\w*|bitecek\w*|bitiyor\w*|dolan\w*|dolacak\w*|planla\w*)\b/', $text);
        $valid = (bool) preg_match('/\b(gecerli|aktif|(?:kaskosu|policesi|sigortasi|vizesi|muayenesi|egzozu) (?:olan|var)|kaskolu\w*)\b/', $text);
        $intent = match ($kind) {
            'date' => match (true) {
                $missing => 'missing', $expired => 'expired', $nearest => 'nearest',
                str_contains($text, 'yarin') => 'tomorrow',
                $amount !== null || $upcoming || str_contains($text, 'bugun') => 'upcoming',
                $valid => 'valid',
                default => null,
            },
            'maintenance' => match (true) {
                $expired => 'expired', $nearest => 'nearest', $amount !== null || $upcoming || str_contains($text, 'bakim') => 'upcoming', default => null,
            },
            'fuel' => match (true) {
                (bool) preg_match('/\b(en (?:cok|fazla|yuksek)|en cok yakan)\b/', $text) => 'highest',
                str_contains($text, 'yakit') || str_contains($text, 'tuket') || $amount !== null => 'high',
                default => null,
            },
        };
        // Ignore conversation unless the message looks like a fleet query or explicitly addresses the bot.
        if ($intent !== null && ($directed || $count || ($request && ($topic || $amount !== null || $nearest || $expired || $missing || $upcoming)))) {
            return $this->validated($kind, ['intent' => $intent, 'amount' => str_contains($text, 'bugun') && $kind === 'date' ? 0 : $amount, 'count' => $count]);
        }

        $isQuestion = (bool) preg_match('/\b(hang\w*|liste\w*|goster\w*|cikar\w*|bul\w*)\b/', $text);
        if (!$directed && !($topic && $request) && !$isQuestion) {
            return null;
        }

        $interpreted = $this->model->interpret($kind, $original);
        if ($interpreted === null && !$directed && !$topic) {
            return null;
        }

        return $this->validated($kind, ($interpreted ?? ['intent' => 'help']) + ['amount' => $amount, 'count' => $count]);
    }

    private function validated(string $kind, array $value): array
    {
        $intent = $value['intent'] ?? 'help';
        $amount = $value['amount'] ?? null;
        $max = match ($kind) {'maintenance' => 100000, 'fuel' => 90, default => 365};
        if (!in_array($intent, self::intents($kind), true) || ($amount !== null && (!is_int($amount) || $amount < 0 || $amount > $max))
            || ($intent === 'vehicle' && !isset($value['plate']))) {
            return ['intent' => 'help'];
        }

        return ['intent' => $intent, 'amount' => $amount, 'plate' => $value['plate'] ?? null, 'count' => ($value['count'] ?? false) === true];
    }

    private function explicitTopic(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(?:egzoz|eksoz|egzos)\w*\b/', $text) => 'exhaust',
            (bool) preg_match('/\bimm\w*\b|ihtiyari mali/', $text) => 'imm',
            (bool) preg_match('/\bkasko\w*\b/', $text) => 'kasko',
            (bool) preg_match('/\b(?:vize|muayene)\w*\b/', $text) => 'inspection',
            (bool) preg_match('/\bsigorta\w*\b/', $text) => 'insurance',
            (bool) preg_match('/\b(?:yakit|mazot|tuketim)\w*\b/', $text) => 'fuel',
            (bool) preg_match('/\b(?:bakim|servis|yaglama)\w*\b/', $text) => 'maintenance',
            default => null,
        };
    }

    private function plate(string $text): ?string
    {
        if (preg_match(self::PLATE, $text, $match) && !in_array(strtoupper($match[2]), ['GUN', 'MAY', 'MAR', 'EKI', 'KM'], true)) {
            return strtoupper($match[1] . $match[2] . $match[3]);
        }

        return null;
    }

    private function amount(string $text, string $unit): ?int
    {
        $words = implode('|', array_keys(self::NUMBERS));
        if (!preg_match('/\b(\d{1,3}(?:\.\d{3})+|\d+|(?:' . $words . ')(?:\s+(?:' . $words . '))*)\s*(' . $unit . ')\b/', $text, $match)) {
            return null;
        }
        $value = 0;
        $digits = str_replace('.', '', $match[1]);
        if (ctype_digit($digits)) {
            $value = (int) $digits;
        } else {
            foreach (explode(' ', $match[1]) as $word) {
                $number = self::NUMBERS[$word];
                $value = $number >= 100 ? max(1, $value) * $number : $value + $number;
            }
        }

        return str_starts_with($match[2], 'hafta') ? min($value, 100000) * 7 : $value;
    }
}
