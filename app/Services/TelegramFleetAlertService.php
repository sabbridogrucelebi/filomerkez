<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use App\Models\TelegramAlertDelivery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramFleetAlertService
{
    public function __construct(private TelegramKaskoNotificationService $telegram, private TelegramAlertBotSettings $settings)
    {
    }

    public function sendScheduledNotifications(): int
    {
        if (!$this->settings->available()) {
            return 0;
        }

        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();
        $sent = 0;
        TelegramAlertBot::where('is_enabled', true)->eachById(function (TelegramAlertBot $bot) use ($today, &$sent) {
            try {
                if ($bot->hasCredentials() && isset(TelegramAlertBot::CHANNELS[$bot->channel])) {
                    $sent += $this->sendForBot($bot, $today);
                }
            } catch (Throwable $exception) {
                Log::warning('Telegram filo uyarıları işlenemedi.', ['bot_id' => $bot->id, 'error_type' => $exception::class]);
            }
        });

        return $sent;
    }

    public function sendTest(TelegramAlertBot $bot): void
    {
        $label = e(TelegramAlertBot::CHANNELS[$bot->channel]['label']);
        $this->telegram->sendTextMessage($bot, "✅ <b>{$label} · Telegram bağlantı testi</b>\n\n<i>Filo Merkez | Filo Takip Sistemleri</i>");
    }

    private function sendForBot(TelegramAlertBot $bot, Carbon $today): int
    {
        $channel = TelegramAlertBot::CHANNELS[$bot->channel];
        $kind = $channel['kind'];
        $days = TelegramAlertSchedule::days($bot->reminder_days ?? TelegramAlertSchedule::DEFAULT_DAYS);
        $sent = 0;
        Vehicle::where('company_id', $bot->company_id)->where('is_active', true)
            ->when($kind === 'date', fn ($query) => $query->with('alertDocuments'))
            ->when($kind === 'maintenance', fn ($query) => $query->with('maintenanceSetting'))
            ->when($kind === 'fuel', fn ($query) => $query->where('min_km_per_liter', '>', 0))
            ->eachById(function (Vehicle $vehicle) use ($bot, $channel, $kind, $days, $today, &$sent) {
                $events = match ($kind) {
                    'date' => $this->dateEvents($vehicle, $bot->channel, $days, $today),
                    'maintenance' => $this->maintenanceEvents($vehicle, $bot->maintenance_km ?? 200, $today),
                    'fuel' => $this->fuelEvents($vehicle, $today),
                };
                foreach ($events as $key => $details) {
                    $delivery = ['company_id' => $bot->company_id, 'bot_id' => $bot->id,
                        'vehicle_id' => $vehicle->id, 'event_key' => $key];
                    if (TelegramAlertDelivery::where($delivery)->exists()) {
                        continue;
                    }
                    try {
                        $this->telegram->sendTextMessage($bot, $this->message($vehicle, $channel, $details));
                        TelegramAlertDelivery::create($delivery);
                        $sent++;
                    } catch (Throwable $exception) {
                        Log::warning('Telegram araç uyarısı gönderilemedi.', [
                            'bot_id' => $bot->id, 'vehicle_id' => $vehicle->id, 'error_type' => $exception::class,
                        ]);
                    }
                }
            });

        return $sent;
    }

    private function dateEvents(Vehicle $vehicle, string $channel, array $days, Carbon $today): array
    {
        $types = Vehicle::ALERT_DOC_TYPES[$channel] ?? match ($channel) {
            'imm' => ['İMM Poliçesi', 'İMM POLİÇESİ'],
            'exhaust' => ['Egzoz', 'Egzoz Emisyon'],
        };
        $fallback = Vehicle::ALERT_FALLBACK_FIELDS[$channel] ?? ($channel === 'exhaust' ? 'exhaust_date' : null);
        $document = $vehicle->alertDocuments->where('company_id', $vehicle->company_id)
            ->whereIn('document_type', $types)->sortByDesc('end_date')->first();
        $end = $document?->end_date ?? ($fallback ? $vehicle->{$fallback} : null);
        if (!$end) {
            return [];
        }

        $remaining = (int) $today->diffInDays(Carbon::parse($end->toDateString(), $today->timezone));
        $threshold = $remaining >= 0 ? TelegramAlertSchedule::dueThreshold($days, $remaining) : null;
        if ($threshold === null) {
            return [];
        }

        return ['date:' . $end->toDateString() . ':' . $threshold => '📅 Bitiş: ' . $end->format('d.m.Y')
            . ($remaining === 0 ? "\n⏳ <b>Bugün sona eriyor</b>" : "\n⏳ <b>{$remaining} gün kaldı</b>")];
    }

    private function maintenanceEvents(Vehicle $vehicle, int $threshold, Carbon $today): array
    {
        $status = $vehicle->maintenance_status;
        $records = $vehicle->maintenances()->where('company_id', $vehicle->company_id)
            ->where('status', 'completed')->whereDate('service_date', '<=', $today->toDateString())
            ->get()->unique('maintenance_type');
        $currentKm = $status['current_km'] ?? max($vehicle->current_km, $records->max('km'), $vehicle->fuels()->max('km'));
        $events = [];
        foreach ($records as $record) {
            $remainingKm = match ($record->maintenance_type) {
                'YAĞ BAKIMI' => $status['oil_remaining'] ?? null,
                'ALT YAĞLAMA' => $status['lube_remaining'] ?? null,
                default => null,
            };
            if ($record->next_service_km > 0) {
                $remainingKm = $record->next_service_km - $currentKm;
            }
            $dueByKm = $remainingKm !== null && $remainingKm <= $threshold;
            $dueByDate = $record->next_service_date && $record->next_service_date->toDateString() <= $today->toDateString();
            if (!$dueByKm && !$dueByDate) {
                continue;
            }

            $details = '🛠️ ' . e($record->maintenance_type ?: $record->title ?: 'Planlanan bakım');
            if ($dueByKm) {
                $details .= $remainingKm >= 0 ? "\n📍 {$remainingKm} km kaldı" : "\n📍 Bakım kilometresi " . abs($remainingKm) . ' km aşıldı';
            }
            if ($dueByDate) {
                $details .= "\n📅 Planlanan tarih: " . $record->next_service_date->format('d.m.Y');
            }
            $events['maintenance:' . $record->id] = $details;
        }

        return $events;
    }

    private function fuelEvents(Vehicle $vehicle, Carbon $today): array
    {
        $from = $today->copy()->subDay()->toDateString();
        $fuels = $vehicle->fuels()->where('company_id', $vehicle->company_id)->orderByDesc('km')->orderByDesc('id');
        $recent = (clone $fuels)->whereBetween('date', [$from, $today->toDateString()])->get()->reverse();
        if ($recent->isEmpty()) {
            return [];
        }
        $previous = $fuels->whereDate('date', '<', $from)->first();
        $events = [];
        $limit = number_format($vehicle->min_km_per_liter, 2, ',', '.');
        foreach ($recent as $fuel) {
            $distance = $previous?->km > 0 && $fuel->km > 0 ? $fuel->km - $previous->km : 0;
            $previous = $fuel;
            if ($distance <= 0 || $fuel->liters <= 0) {
                continue;
            }
            $efficiency = $distance / (float) $fuel->liters;
            if ($efficiency < $vehicle->min_km_per_liter) {
                $actual = number_format($efficiency, 2, ',', '.');
                $events['fuel:' . $fuel->id] = '📅 Yakıt alımı: ' . $fuel->date->format('d.m.Y')
                    . "\n⛽ Ölçülen: <b>{$actual} km/L</b>\n📉 Araç alt sınırı: {$limit} km/L";
            }
        }

        return $events;
    }

    private function message(Vehicle $vehicle, array $channel, string $details): string
    {
        $name = trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? ''));

        return $channel['icon'] . ' <b>' . e($channel['label']) . " uyarısı</b>\n\n"
            . '🚘 <b>' . e($vehicle->plate) . '</b>' . ($name !== '' ? ' — ' . e($name) : '')
            . "\n\n{$details}\n\n<i>Filo Merkez | Filo Takip Sistemleri</i>";
    }
}
