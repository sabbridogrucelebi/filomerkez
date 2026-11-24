<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Fleet\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TelegramPolicyAlerts
{
    public static function latestUploaded(Vehicle $vehicle, string $relation): ?Document
    {
        return $vehicle->{$relation}->first(fn (Document $document) => (int) $document->company_id === (int) $vehicle->company_id
            && filled($document->file_path) && $document->end_date);
    }

    public static function daysUntil(Carbon $today, Carbon $expiryDate): int
    {
        $end = Carbon::parse($expiryDate->toDateString(), $today->timezone);

        return (int) $today->diffInDays($end, false);
    }

    public static function relationFor(string $channel): string
    {
        return match ($channel) {
            'kasko' => 'kaskoDocuments',
            'imm' => 'immDocuments',
            default => 'alertDocuments',
        };
    }

    /** @return array{0: ?Document, 1: ?Carbon} */
    public static function currentDates(Vehicle $vehicle, string $channel): array
    {
        $relation = self::relationFor($channel);
        if ($relation !== 'alertDocuments') {
            $document = self::latestUploaded($vehicle, $relation);

            return [$document, $document?->end_date];
        }

        $types = Vehicle::ALERT_DOC_TYPES[$channel] ?? ['Egzoz', 'Egzoz Emisyon'];
        $fallback = Vehicle::ALERT_FALLBACK_FIELDS[$channel] ?? 'exhaust_date';
        $document = $vehicle->alertDocuments->where('company_id', $vehicle->company_id)
            ->whereIn('document_type', $types)->sortByDesc('end_date')->first();

        return [$document, $document?->end_date ?? $vehicle->{$fallback}];
    }

    public static function upcoming(int $companyId, string $channel, Carbon $today, int $window): Collection
    {
        return self::records($companyId, $channel, $today)->filter(fn (array $entry) => $entry['days'] !== null
            && $entry['days'] >= 0 && $entry['days'] <= $window)->sortBy('days')->values();
    }

    public static function records(int $companyId, string $channel, Carbon $today): Collection
    {
        return Vehicle::where('company_id', $companyId)->where('is_active', true)->with(self::relationFor($channel))->orderBy('id')->get()
            ->map(function (Vehicle $vehicle) use ($channel, $today) {
                [$policy, $end] = self::currentDates($vehicle, $channel);

                return ['vehicle' => $vehicle, 'policy' => $policy, 'end' => $end, 'days' => $end ? self::daysUntil($today, $end) : null];
            });
    }

    public static function withOptionalStart(string $template, ?string $start, string $endField, string $label = 'Poliçe başlangıcı'): string
    {
        $field = '{{policy_start_line}}';
        if (!$start) {
            return str_replace([$field . "\r\n", $field . "\n", $field], '', $template);
        }

        $line = '📅 <b>' . e($label) . ':</b> ' . e($start);
        if (str_contains($template, $field)) {
            return str_replace($field, $line, $template);
        }
        $expiry = strpos($template, $endField);
        if ($expiry !== false) {
            $lineStart = strrpos(substr($template, 0, $expiry), "\n");

            return substr_replace($template, $line . "\n", $lineStart === false ? 0 : $lineStart + 1, 0);
        }

        return rtrim($template) . "\n\n" . $line;
    }

    public static function summaryMessages(string $title, Collection $entries): array
    {
        $heading = '📋 <b>' . e($title) . "</b>\nToplam: <b>" . $entries->count() . ' araç</b>';
        $messages = [];
        $current = $heading;
        foreach ($entries as $item) {
            ['vehicle' => $vehicle, 'policy' => $policy, 'end' => $end, 'days' => $days] = $item;
            $entry = '🚘 <b>' . e($vehicle->plate ?: '-') . '</b> — ' . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-')
                . "\n👤 Ruhsat sahibi: " . e($vehicle->license_owner ?: '-')
                . "\n🏢 Vergi / T.C. No: " . e($vehicle->owner_tax_or_tc_no ?: '-')
                . ($policy?->start_date ? "\n📅 Poliçe başlangıcı: " . $policy->start_date->format('d.m.Y') : '')
                . "\n🛡️ Bitiş: " . $end->format('d.m.Y')
                . "\n⏳ <b>{$days} gün kaldı</b>";

            if (mb_strlen($current . "\n\n" . $entry) > 3500 && $current !== $heading) {
                $messages[] = $current;
                $current = $heading;
            }
            $current .= "\n\n" . $entry;
        }
        if ($current !== $heading) {
            $messages[] = $current;
        }

        return $messages;
    }
}
