<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use Carbon\Carbon;

final class TelegramFleetMeasurements
{
    public static function maintenance(Vehicle $vehicle, Carbon $today): array
    {
        $date = $today->toDateString();
        $records = $vehicle->maintenances()->where('company_id', $vehicle->company_id)
            ->whereDate('service_date', '<=', $date);
        $latest = (clone $records)->where('status', 'completed')->get()->unique('maintenance_type');
        if ($latest->isEmpty()) {
            return [];
        }

        $currentKm = max((int) $vehicle->current_km, (int) $records->max('km'),
            (int) $vehicle->fuels()->where('company_id', $vehicle->company_id)->whereDate('date', '<=', $date)->max('km'));
        $setting = $vehicle->maintenanceSetting;
        if ($setting && (int) $setting->company_id !== (int) $vehicle->company_id) {
            $setting = null;
        }

        $plans = [];
        foreach ($latest as $record) {
            $interval = (int) match ($record->maintenance_type) {
                'YAĞ BAKIMI' => $setting?->oil_change_interval_km,
                'ALT YAĞLAMA' => $setting?->under_lubrication_interval_km,
                default => null,
            };
            $nextKm = (int) $record->next_service_km;
            if ($nextKm <= 0 && $interval > 0 && $record->km > 0) {
                $nextKm = $record->km + $interval;
            }
            if ($nextKm <= 0 && !$record->next_service_date) {
                continue;
            }

            $plans[$record->id] = ['type' => $record->maintenance_type ?: $record->title ?: 'Planlanan bakım',
                'remaining_km' => $nextKm > 0 ? $nextKm - $currentKm : null, 'date' => $record->next_service_date,
                'current_km' => $currentKm];
        }

        return $plans;
    }

    public static function fuel(Vehicle $vehicle, Carbon $from, Carbon $today): array
    {
        $firstDate = $from->toDateString();
        $fuels = $vehicle->fuels()->where('company_id', $vehicle->company_id)->orderByDesc('km')->orderByDesc('id');
        $recent = (clone $fuels)->whereBetween('date', [$firstDate, $today->toDateString()])->get()->reverse();
        if ($recent->isEmpty()) {
            return [];
        }
        $previous = $fuels->whereDate('date', '<', $firstDate)->first();
        $measurements = [];
        foreach ($recent as $fuel) {
            $distance = $previous?->km > 0 && $fuel->km > 0 ? $fuel->km - $previous->km : 0;
            $previous = $fuel;
            if ($distance > 0 && $fuel->liters > 0) {
                $measurements[$fuel->id] = ['date' => $fuel->date, 'distance' => $distance,
                    'liters' => (float) $fuel->liters, 'efficiency' => $distance / (float) $fuel->liters];
            }
        }

        return $measurements;
    }
}
