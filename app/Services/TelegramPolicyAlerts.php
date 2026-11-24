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

    public static function upcoming(int $companyId, string $relation, Carbon $today, int $window): Collection
    {
        return Vehicle::where('company_id', $companyId)->where('is_active', true)->with($relation)->orderBy('id')->get()
            ->map(function (Vehicle $vehicle) use ($relation, $today, $window) {
                $policy = self::latestUploaded($vehicle, $relation);
                if (!$policy) {
                    return null;
                }
                $days = self::daysUntil($today, $policy->end_date);

                return $days >= 0 && $days <= $window ? ['vehicle' => $vehicle, 'policy' => $policy, 'days' => $days] : null;
            })->filter()->sortBy('days')->values();
    }

    public static function summaryMessages(string $title, Collection $entries): array
    {
        $heading = '📋 <b>' . e($title) . "</b>\nToplam: <b>" . $entries->count() . ' araç</b>';
        $messages = [];
        $current = $heading;
        foreach ($entries as $item) {
            ['vehicle' => $vehicle, 'policy' => $policy, 'days' => $days] = $item;
            $entry = '🚘 <b>' . e($vehicle->plate ?: '-') . '</b> — ' . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-')
                . "\n👤 Ruhsat sahibi: " . e($vehicle->license_owner ?: '-')
                . "\n🏢 Vergi / T.C. No: " . e($vehicle->owner_tax_or_tc_no ?: '-')
                . "\n📅 Poliçe başlangıcı: " . ($policy->start_date?->format('d.m.Y') ?? 'Kayıtlı değil')
                . "\n🛡️ Bitiş: " . $policy->end_date->format('d.m.Y')
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
