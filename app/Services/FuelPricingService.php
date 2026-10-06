<?php

namespace App\Services;

use App\Models\Fuel;
use App\Models\FuelStation;

class FuelPricingService
{
    /**
     * Birim fiyat pompa fiyatıdır ve KDV dahildir.
     * İskontosuz brüt KDV hariç tutulur; ödenecek tutar iskonto sonrası KDV dahil kalır.
     * KDV, ödenecek tutarın içinden ayrılır, üzerine eklenmez.
     */
    public function calculate(
        float $liters,
        float $pricePerLiter,
        float $vatRate = 0,
        ?string $discountType = null,
        float $discountValue = 0
    ): array {
        $kdvDahilGross = round($liters * $pricePerLiter, 2);

        $kdvDahilDiscount = 0;
        if ($discountValue > 0) {
            if ($discountType === 'percentage') {
                $kdvDahilDiscount = round($kdvDahilGross * ($discountValue / 100), 2);
            } elseif ($discountType === 'fixed') {
                $kdvDahilDiscount = round($discountValue, 2);
            }
        }

        if ($kdvDahilDiscount > $kdvDahilGross) {
            $kdvDahilDiscount = $kdvDahilGross;
        }

        $kdvDahilTotal = round($kdvDahilGross - $kdvDahilDiscount, 2);

        $vatMultiplier = 1 + ($vatRate / 100);
        if ($vatMultiplier <= 0) {
            $vatMultiplier = 1;
        }

        $kdvHaricGross = round($kdvDahilGross / $vatMultiplier, 2);
        $kdvHaricTotal = round($kdvDahilTotal / $vatMultiplier, 2);
        $kdvHaricDiscount = round($kdvDahilDiscount / $vatMultiplier, 2);
        $vatAmount = round($kdvDahilTotal - $kdvHaricTotal, 2);

        return [
            'gross_total_cost' => $kdvHaricGross,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'net_cost' => $kdvHaricTotal,
            'discount_amount' => $kdvHaricDiscount,
            'total_cost' => $kdvDahilTotal,
        ];
    }

    /**
     * Kayıtlı kolonlar eski formülde kalmış olsa bile özeti litre ve pompa fiyatından hesaplar.
     */
    public function summarizeFuels(iterable $fuels, ?FuelStation $station): array
    {
        $gross = 0.0;
        $discount = 0.0;
        $vat = 0.0;
        $net = 0.0;
        $liters = 0.0;
        $paid = 0.0;

        foreach ($fuels as $fuel) {
            $pricing = $this->forStation((float) $fuel->liters, (float) $fuel->price_per_liter, $station);
            $gross += $pricing['gross_total_cost'];
            $discount += $pricing['discount_amount'];
            $vat += $pricing['vat_amount'];
            $net += $pricing['total_cost'];
            $liters += (float) $fuel->liters;
            $paid += (float) $fuel->paid_amount;
        }

        $net = round($net, 2);
        $paid = round($paid, 2);

        return [
            'gross_total' => round($gross, 2),
            'discount_total' => round($discount, 2),
            'vat_total' => round($vat, 2),
            'net_total' => $net,
            'paid_total' => $paid,
            'net_payable' => round(max(0, $net - $paid), 2),
            'total_liters' => round($liters, 2),
        ];
    }

    public function forStation(float $liters, float $pricePerLiter, ?FuelStation $station): array
    {
        return $this->calculate(
            $liters,
            $pricePerLiter,
            (float) ($station?->vat_rate ?? 0),
            $station?->discount_type,
            (float) ($station?->discount_value ?? 0)
        );
    }

    public function repriceFuel(Fuel $fuel, ?FuelStation $station = null): void
    {
        $station ??= $fuel->station;

        $fuel->forceFill(
            $this->forStation((float) $fuel->liters, (float) $fuel->price_per_liter, $station)
        )->saveQuietly();
    }

    public function repriceStation(FuelStation $station): void
    {
        Fuel::query()
            ->where('fuel_station_id', $station->id)
            ->orderBy('id')
            ->chunkById(200, function ($fuels) use ($station) {
                foreach ($fuels as $fuel) {
                    $this->repriceFuel($fuel, $station);
                }
            });
    }

    public function repriceAll(): void
    {
        Fuel::query()
            ->with('station')
            ->orderBy('id')
            ->chunkById(200, function ($fuels) {
                foreach ($fuels as $fuel) {
                    $this->repriceFuel($fuel, $fuel->station);
                }
            });
    }
}
