<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use App\Models\TelegramAlertDelivery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramFleetAlertService
{
    public function __construct(
        private TelegramKaskoNotificationService $telegram,
        private TelegramAlertBotSettings $settings,
        private TelegramFleetAlertMessage $messages,
    )
    {
    }

    public function sendScheduledNotifications(): int
    {
        if (!$this->settings->available()) {
            return 0;
        }

        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();
        $sent = 0;
        TelegramAlertBot::with('company')->where('is_enabled', true)->eachById(function (TelegramAlertBot $bot) use ($today, &$sent) {
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

    public function sendTest(TelegramAlertBot $bot): ?int
    {
        $today = today(TelegramAlertSchedule::TIMEZONE);
        if ($bot->channel === 'imm') {
            if (!$bot->hasCredentials()) {
                throw new RuntimeException('Önce bot anahtarını ve sohbet kimliğini kaydedin.');
            }

            [$vehicles] = $this->sendImmSummary($bot, $today, manual: true);

            return $vehicles;
        }

        $sample = new Vehicle([
            'plate' => 'ÖRNEK-42', 'brand' => 'Örnek', 'model' => 'Araç', 'model_year' => 2024,
            'license_serial_no' => 'ÖRNEK-001', 'chassis_no' => 'ÖRNEK-ŞASE',
            'license_owner' => 'Örnek Filo', 'owner_tax_or_tc_no' => '—',
        ]);
        $event = match (TelegramAlertBot::CHANNELS[$bot->channel]['kind']) {
            'date' => ['end_date' => $today->copy()->addDays(5)->format('d.m.Y'), 'days_left' => 5, 'expiry_status' => '5 gün kaldı'],
            'maintenance' => ['maintenance_type' => 'Periyodik bakım', 'maintenance_status' => '150 km kaldı',
                'current_km' => '88.350', 'next_service_date' => $today->copy()->addDays(5)->format('d.m.Y')],
            'fuel' => ['fuel_date' => $today->format('d.m.Y'), 'distance_km' => '256', 'liters' => '40,00',
                'measured_km_per_liter' => '6,40', 'minimum_km_per_liter' => '8,00'],
        };

        $this->telegram->sendTextMessage($bot, "🧪 <b>TEST MESAJI</b> · Örnek veriler\n\n" . $this->messages->render($bot, $sample, $event));

        return null;
    }

    private function sendForBot(TelegramAlertBot $bot, Carbon $today): int
    {
        $kind = TelegramAlertBot::CHANNELS[$bot->channel]['kind'];
        $days = TelegramAlertSchedule::days($bot->reminder_days ?? TelegramAlertSchedule::DEFAULT_DAYS);
        $sent = 0;
        if ($bot->channel === 'imm' && $bot->upcoming_summary_enabled) {
            try {
                [, $sent] = $this->sendImmSummary($bot, $today, manual: false);
            } catch (Throwable $exception) {
                Log::warning('Telegram İMM özeti gönderilemedi.', ['bot_id' => $bot->id, 'error_type' => $exception::class]);
            }
        }
        Vehicle::where('company_id', $bot->company_id)->where('is_active', true)
            ->when($kind === 'date', fn ($query) => $query->with($bot->channel === 'imm' ? 'immDocuments' : 'alertDocuments'))
            ->when($kind === 'maintenance', fn ($query) => $query->with('maintenanceSetting'))
            ->when($kind === 'fuel', fn ($query) => $query->where('min_km_per_liter', '>', 0))
            ->eachById(function (Vehicle $vehicle) use ($bot, $kind, $days, $today, &$sent) {
                $events = match ($kind) {
                    'date' => $this->dateEvents($vehicle, $bot->channel, $days, $today),
                    'maintenance' => $this->maintenanceEvents($vehicle, $bot->maintenance_km ?? 200, $today),
                    'fuel' => $this->fuelEvents($vehicle, $today),
                };
                foreach ($events as $key => $event) {
                    $delivery = ['company_id' => $bot->company_id, 'bot_id' => $bot->id,
                        'vehicle_id' => $vehicle->id, 'event_key' => $key];
                    if (TelegramAlertDelivery::where($delivery)->exists()) {
                        continue;
                    }
                    try {
                        $this->telegram->sendTextMessage($bot, $this->messages->render($bot, $vehicle, $event));
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

    /** @return array{0: int, 1: int} Matching vehicles and messages sent by this call. */
    private function sendImmSummary(TelegramAlertBot $bot, Carbon $today, bool $manual): array
    {
        $lockKey = 'telegram:imm:summary:' . $bot->id;
        $result = Cache::lock($lockKey, 300)->get(function () use ($bot, $today, $manual, $lockKey) {
            if (!$manual && $bot->last_upcoming_summary_date?->isSameDay($today)) {
                return [0, 0];
            }

            $window = max(TelegramAlertSchedule::days($bot->reminder_days ?? []) ?: TelegramAlertSchedule::DEFAULT_DAYS);
            $entries = TelegramPolicyAlerts::upcoming($bot->company_id, 'immDocuments', $today, $window);
            $title = $manual ? "İMM KONTROLÜ · {$window} GÜN İÇİNDE BİTECEK POLİÇELER" : "{$window} GÜN İÇİNDE BİTECEK İMM POLİÇELERİ";
            $messages = TelegramPolicyAlerts::summaryMessages($title, $entries);
            $progressKey = $lockKey . ':' . $today->toDateString() . ':' . hash('sha256', $bot->chat_id . serialize($messages));
            $next = (int) Cache::get($progressKey, 0);
            $sent = 0;
            foreach (array_slice($messages, $next) as $message) {
                $this->telegram->sendTextMessage($bot, $message);
                Cache::put($progressKey, ++$next, $today->copy()->addDay());
                $sent++;
            }

            if (!$manual) {
                $bot->last_upcoming_summary_date = $today;
                $bot->save();
            }
            Cache::forget($progressKey);

            return [$entries->count(), $sent];
        });

        if ($result === false) {
            throw new RuntimeException('İMM kontrolü zaten çalışıyor. Biraz sonra tekrar deneyin.');
        }

        return $result;
    }

    private function dateEvents(Vehicle $vehicle, string $channel, array $days, Carbon $today): array
    {
        if ($channel === 'imm') {
            $end = TelegramPolicyAlerts::latestUploaded($vehicle, 'immDocuments')?->end_date;
        } else {
            $types = Vehicle::ALERT_DOC_TYPES[$channel] ?? ['Egzoz', 'Egzoz Emisyon'];
            $fallback = Vehicle::ALERT_FALLBACK_FIELDS[$channel] ?? 'exhaust_date';
            $document = $vehicle->alertDocuments->where('company_id', $vehicle->company_id)
                ->whereIn('document_type', $types)->sortByDesc('end_date')->first();
            $end = $document?->end_date ?? $vehicle->{$fallback};
        }
        if (!$end) {
            return [];
        }

        $remaining = TelegramPolicyAlerts::daysUntil($today, $end);
        $threshold = $channel === 'imm'
            ? TelegramAlertSchedule::policyThreshold($days, $remaining)
            : ($remaining >= 0 ? TelegramAlertSchedule::dueThreshold($days, $remaining) : null);
        if ($threshold === null) {
            return [];
        }

        return ['date:' . $end->toDateString() . ':' . $threshold => [
            'end_date' => $end->format('d.m.Y'),
            'days_left' => abs($remaining),
            'expired' => $remaining < 0,
            'expiry_status' => match (true) {
                $remaining < 0 => abs($remaining) . ' gün önce sona erdi',
                $remaining === 0 => 'Bugün sona eriyor',
                default => "{$remaining} gün kaldı",
            },
        ]];
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

            $events['maintenance:' . $record->id] = [
                'maintenance_type' => $record->maintenance_type ?: $record->title ?: 'Planlanan bakım',
                'current_km' => number_format($currentKm, 0, ',', '.'),
                'next_service_date' => $record->next_service_date?->format('d.m.Y'),
                'maintenance_status' => match (true) {
                    !$dueByKm => 'Planlanan bakım tarihi geldi',
                    $remainingKm < 0 => 'Bakım kilometresi ' . abs($remainingKm) . ' km aşıldı',
                    default => "{$remainingKm} km kaldı",
                },
            ];
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
        foreach ($recent as $fuel) {
            $distance = $previous?->km > 0 && $fuel->km > 0 ? $fuel->km - $previous->km : 0;
            $previous = $fuel;
            if ($distance <= 0 || $fuel->liters <= 0) {
                continue;
            }
            $efficiency = $distance / (float) $fuel->liters;
            if ($efficiency < $vehicle->min_km_per_liter) {
                $events['fuel:' . $fuel->id] = [
                    'fuel_date' => $fuel->date->format('d.m.Y'),
                    'distance_km' => number_format($distance, 0, ',', '.'),
                    'liters' => number_format((float) $fuel->liters, 2, ',', '.'),
                    'measured_km_per_liter' => number_format($efficiency, 2, ',', '.'),
                    'minimum_km_per_liter' => number_format($vehicle->min_km_per_liter, 2, ',', '.'),
                ];
            }
        }

        return $events;
    }

}
