<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use App\Models\TelegramKaskoNotificationSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TelegramAssistantAnswer
{
    public function messages(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, string $channel, array $question): array
    {
        $label = $channel === 'kasko' ? 'Kasko' : TelegramAlertBot::CHANNELS[$channel]['label'];
        $intent = $question['intent'];
        if ($intent === 'help' || $intent === 'topic') {
            $intro = $intent === 'topic' ? 'Bu grup ' . e($label) . ' soruları için ayarlı.' : 'Soruyu netleştirir misiniz?';

            return ['💬 <b>' . $intro . "</b>\n" . implode("\n", array_map(fn (string $example) => '• ' . e($example), TelegramAssistantQuestion::examples($channel)))];
        }

        $today = today(TelegramAlertSchedule::TIMEZONE);
        [$title, $entries] = match (TelegramAssistantQuestion::kind($channel)) {
            'date' => $this->dates($bot, $channel, $label, $question, $today),
            'maintenance' => $this->maintenance($bot, $question, $today),
            'fuel' => $this->fuel($bot, $question, $today),
        };
        $heading = '📋 <b>' . e($title) . '</b>';
        if ($entries->isEmpty()) {
            return [$heading . "\nBu sorguya uyan araç kaydı bulunamadı.\nToplam: <b>0 araç</b>"];
        }

        $count = $entries->pluck('vehicle.id')->unique()->count();
        $heading .= "\nToplam: <b>{$count} araç</b>";
        if ($question['count'] ?? false) {
            return [$heading];
        }

        $messages = [];
        $current = $heading;
        foreach ($entries as $entry) {
            $vehicle = $entry['vehicle'];
            $name = trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? ''));
            $line = '🚘 <b>' . e($vehicle->plate) . '</b>' . ($name !== '' ? ' — ' . e($name) : '') . "\n" . $entry['detail'];
            if (mb_strlen($current . "\n\n" . $line) > 3200 && $current !== $heading) {
                $messages[] = $current;
                $current = $heading;
            }
            $current .= "\n\n" . $line;
        }
        $messages[] = $current;

        return $messages;
    }

    private function dates(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, string $channel, string $label, array $q, Carbon $today): array
    {
        $window = $q['amount'] ?? max(TelegramAlertSchedule::days($bot->reminder_days ?? []) ?: TelegramAlertSchedule::DEFAULT_DAYS);
        $records = TelegramPolicyAlerts::records($bot->company_id, $channel, $today);
        $upcoming = $records->filter(fn (array $entry) => $entry['days'] !== null && $entry['days'] >= 0)->sortBy('days');
        [$title, $selected] = match ($q['intent']) {
            'upcoming' => [($window === 0 ? 'Bugün bitecek' : "{$window} gün içinde bitecek") . " · {$label}",
                $upcoming->where('days', '<=', $window)],
            'tomorrow' => ["Yarın bitecek · {$label}", $upcoming->where('days', 1)],
            'nearest' => ["Bitişi en yakın araçlar · {$label}", $upcoming->isEmpty() ? $upcoming : $upcoming->where('days', $upcoming->first()['days'])],
            'expired' => [isset($q['amount']) ? "Son {$window} günde süresi geçenler · {$label}" : "Süresi geçenler · {$label}",
                $records->filter(fn (array $entry) => $entry['days'] !== null && $entry['days'] < 0
                    && (!isset($q['amount']) || $entry['days'] >= -$window))->sortByDesc('days')],
            'missing' => ["Tarihi tanımsız araçlar · {$label}", $records->whereNull('days')],
            'valid' => ["Geçerlilik tarihi devam eden araçlar · {$label}", $upcoming],
            'vehicle' => ["{$q['plate']} · {$label}", $records->filter(fn (array $entry) => $this->samePlate($entry['vehicle'], $q['plate']))],
        };

        return [$title, $selected->map(function (array $entry) {
            $days = $entry['days'];
            $detail = match (true) {
                $days === null => '📅 Geçerlilik tarihi kayıtlı değil.',
                $days === 0 => '⏳ Bugün sona eriyor',
                $days < 0 => '⌛ ' . abs($days) . ' gün önce sona erdi',
                default => "⏳ {$days} gün kaldı",
            };
            if ($entry['end']) {
                $detail = '📅 ' . $entry['end']->format('d.m.Y') . ' · ' . $detail;
            }
            if ($entry['policy']?->start_date) {
                $detail = '📆 Başlangıç: ' . $entry['policy']->start_date->format('d.m.Y') . "\n" . $detail;
            }

            return ['vehicle' => $entry['vehicle'], 'detail' => $detail];
        })];
    }

    private function maintenance(TelegramAlertBot $bot, array $q, Carbon $today): array
    {
        $limit = $q['amount'] ?? $bot->maintenance_km ?? 200;
        $intent = $q['intent'];
        $rows = collect();
        foreach ($this->vehicles($bot) as $vehicle) {
            if ($intent === 'vehicle' && !$this->samePlate($vehicle, $q['plate'])) {
                continue;
            }
            foreach (TelegramFleetMeasurements::maintenance($vehicle, $today) as $plan) {
                $km = $plan['remaining_km'];
                $days = $plan['date'] ? TelegramPolicyAlerts::daysUntil($today, $plan['date']) : null;
                $overdue = ($km !== null && $km < 0) || ($days !== null && $days < 0);
                $include = match ($intent) {
                    'expired' => $overdue,
                    'nearest' => !$overdue,
                    'upcoming' => !$overdue && (($km !== null && $km <= $limit) || (!isset($q['amount']) && $days === 0)),
                    'vehicle' => true,
                };
                if (!$include) {
                    continue;
                }

                $timing = match (true) {
                    $km === null => '',
                    $km < 0 => ' · ' . number_format(abs($km), 0, ',', '.') . ' km aşıldı',
                    default => ' · ' . number_format($km, 0, ',', '.') . ' km kaldı',
                };
                $detail = '🔧 ' . e($plan['type']) . $timing . ($plan['date'] ? ' · 📅 ' . $plan['date']->format('d.m.Y') : '');
                $rows->push(['vehicle' => $vehicle, 'detail' => $detail, 'km' => $km, 'days' => $days]);
            }
        }
        $title = match ($intent) {
            'expired' => 'Bakımı geciken araçlar', 'nearest' => 'Bakıma en yakın araçlar',
            'vehicle' => $q['plate'] . ' · Bakım planı', default => "Bakıma {$limit} km veya daha az kalan araçlar",
        };
        if ($intent === 'nearest' && $rows->isNotEmpty()) {
            $withKm = $rows->whereNotNull('km');
            $rows = $withKm->isNotEmpty() ? $withKm->where('km', $withKm->min('km')) : $rows->where('days', $rows->min('days'));
            $title .= $withKm->isNotEmpty() ? ' · kilometreye göre' : ' · tarihe göre';
        }

        return [$title, $rows->sortBy(fn (array $row) => [$row['km'] ?? PHP_INT_MAX, $row['days'] ?? PHP_INT_MAX])];
    }

    private function fuel(TelegramAlertBot $bot, array $q, Carbon $today): array
    {
        $days = max(1, $q['amount'] ?? ($q['intent'] === 'vehicle' ? 30 : 7));
        $from = $today->copy()->subDays($days - 1);
        $rows = collect();
        foreach ($this->vehicles($bot) as $vehicle) {
            if ($q['intent'] === 'vehicle' && !$this->samePlate($vehicle, $q['plate'])) {
                continue;
            }
            $limit = (float) $vehicle->min_km_per_liter;
            if ($q['intent'] === 'high' && $limit <= 0) {
                continue;
            }
            $measurements = collect(TelegramFleetMeasurements::fuel($vehicle, $from, $today));
            if ($q['intent'] === 'high') {
                $measurements = $measurements->filter(fn (array $item) => $item['efficiency'] < $limit);
            }
            $record = $q['intent'] === 'vehicle' ? $measurements->last() : $measurements->sortBy('efficiency')->first();
            if (!$record) {
                continue;
            }
            $efficiency = $record['efficiency'];
            $detail = '⛽ ' . number_format(100 / $efficiency, 2, ',', '.') . ' L/100 km · '
                . number_format($efficiency, 2, ',', '.') . ' km/L · ' . $record['date']->format('d.m.Y')
                . ($limit > 0 ? "\nAraç alt sınırı: " . number_format($limit, 2, ',', '.') . ' km/L' : '');
            $rows->push(['vehicle' => $vehicle, 'detail' => $detail, 'efficiency' => $efficiency]);
        }
        $title = match ($q['intent']) {
            'vehicle' => "{$q['plate']} · Son {$days} günde ölçülen yakıt tüketimi",
            'highest' => "Son {$days} günde ölçülen en yüksek yakıt tüketimi",
            default => "Son {$days} günde yakıt verimi sınırın altında kalan araçlar",
        };
        if ($q['intent'] === 'highest' && $rows->isNotEmpty()) {
            $best = $rows->min('efficiency');
            $rows = $rows->filter(fn (array $row) => abs($row['efficiency'] - $best) < 0.000001);
        }

        return [$title, $rows->sortBy('efficiency')];
    }

    private function vehicles(TelegramAlertBot $bot): Collection
    {
        return Vehicle::where('company_id', $bot->company_id)->where('is_active', true)
            ->when($bot->channel === 'maintenance', fn ($query) => $query->with('maintenanceSetting'))->orderBy('id')->get();
    }

    private function samePlate(Vehicle $vehicle, string $plate): bool
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($vehicle->plate ?? '')) === $plate;
    }
}
