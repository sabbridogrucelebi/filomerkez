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

    public function sendScheduledNotifications(bool $scheduled = false): int
    {
        if (!$this->settings->available()) {
            return 0;
        }

        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();
        $sent = 0;
        TelegramAlertBot::with('company')->where('is_enabled', true)->eachById(function (TelegramAlertBot $bot) use ($today, $scheduled, &$sent) {
            if (isset(TelegramAlertBot::CHANNELS[$bot->channel])) {
                $sent += TelegramScheduledCheck::run($bot, $scheduled,
                    fn (TelegramScheduledCheck $check) => $this->sendForBot($bot, $today, $check));
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

    private function sendForBot(TelegramAlertBot $bot, Carbon $today, TelegramScheduledCheck $check): void
    {
        $kind = TelegramAlertBot::CHANNELS[$bot->channel]['kind'];
        $days = TelegramAlertSchedule::days($bot->reminder_days ?? TelegramAlertSchedule::DEFAULT_DAYS);
        if ($bot->channel === 'imm' && $bot->upcoming_summary_enabled) {
            try {
                $this->sendImmSummary($bot, $today, manual: false, check: $check);
            } catch (Throwable $exception) {
                $check->failed($exception);
            }
        }
        Vehicle::where('company_id', $bot->company_id)->where('is_active', true)
            ->when($kind === 'date', fn ($query) => $query->with(TelegramPolicyAlerts::relationFor($bot->channel)))
            ->when($kind === 'maintenance', fn ($query) => $query->with('maintenanceSetting'))
            ->when($kind === 'fuel', fn ($query) => $query->where('min_km_per_liter', '>', 0))
            ->eachById(function (Vehicle $vehicle) use ($bot, $kind, $days, $today, $check) {
                if ($check->shouldPause()) {
                    return false;
                }
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
                        $check->sent++;
                    } catch (Throwable $exception) {
                        $check->failed($exception);
                        Log::warning('Telegram araç uyarısı gönderilemedi.', [
                            'bot_id' => $bot->id, 'vehicle_id' => $vehicle->id, 'error_type' => $exception::class,
                        ]);
                        if ($check->shouldPause()) {
                            return false;
                        }
                    }
                }
            });
    }

    /** @return array{0: int, 1: int} Matching vehicles and messages sent by this call. */
    private function sendImmSummary(TelegramAlertBot $bot, Carbon $today, bool $manual, ?TelegramScheduledCheck $check = null): array
    {
        $lockKey = 'telegram:imm:summary:' . $bot->id;
        $result = Cache::lock($lockKey, 300)->get(function () use ($bot, $today, $manual, $lockKey, $check) {
            if (!$manual && $bot->last_upcoming_summary_date?->isSameDay($today)) {
                return [0, 0];
            }

            $window = max(TelegramAlertSchedule::days($bot->reminder_days ?? []) ?: TelegramAlertSchedule::DEFAULT_DAYS);
            $entries = TelegramPolicyAlerts::upcoming($bot->company_id, 'imm', $today, $window);
            $title = $manual ? "İMM KONTROLÜ · {$window} GÜN İÇİNDE BİTECEK POLİÇELER" : "{$window} GÜN İÇİNDE BİTECEK İMM POLİÇELERİ";
            $messages = TelegramPolicyAlerts::summaryMessages($title, $entries);
            $progressKey = $lockKey . ':' . $today->toDateString() . ':' . hash('sha256', $bot->chat_id . serialize($messages));
            $next = (int) Cache::get($progressKey, 0);
            $sent = 0;
            foreach (array_slice($messages, $next) as $message) {
                $this->telegram->sendTextMessage($bot, $message);
                Cache::put($progressKey, ++$next, $today->copy()->addDay());
                if ($check) {
                    $check->sent++;
                }
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
        [$document, $end] = TelegramPolicyAlerts::currentDates($vehicle, $channel);
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

        return ['date:' . $end->toDateString() . ':' . $threshold => TelegramFleetAlertMessage::dateEvent(
            $end, $remaining, $document,
        )];
    }

    private function maintenanceEvents(Vehicle $vehicle, int $threshold, Carbon $today): array
    {
        $events = [];
        foreach (TelegramFleetMeasurements::maintenance($vehicle, $today) as $id => $plan) {
            $remainingKm = $plan['remaining_km'];
            $dueByKm = $remainingKm !== null && $remainingKm <= $threshold;
            $dueByDate = $plan['date'] && $plan['date']->toDateString() <= $today->toDateString();
            if (!$dueByKm && !$dueByDate) {
                continue;
            }

            $events['maintenance:' . $id] = [
                'maintenance_type' => $plan['type'],
                'current_km' => number_format($plan['current_km'], 0, ',', '.'),
                'next_service_date' => $plan['date']?->format('d.m.Y'),
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
        $events = [];
        foreach (TelegramFleetMeasurements::fuel($vehicle, $today->copy()->subDay(), $today) as $id => $record) {
            if ($record['efficiency'] < $vehicle->min_km_per_liter) {
                $events['fuel:' . $id] = [
                    'fuel_date' => $record['date']->format('d.m.Y'),
                    'distance_km' => number_format($record['distance'], 0, ',', '.'),
                    'liters' => number_format($record['liters'], 2, ',', '.'),
                    'measured_km_per_liter' => number_format($record['efficiency'], 2, ',', '.'),
                    'minimum_km_per_liter' => number_format($vehicle->min_km_per_liter, 2, ',', '.'),
                ];
            }
        }

        return $events;
    }

}
