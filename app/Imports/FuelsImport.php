<?php

namespace App\Imports;

use App\Models\Fuel;
use App\Models\FuelStation;
use App\Models\Fleet\Vehicle;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class FuelsImport implements ToCollection, WithHeadingRow
{
    private function parseDate($value)
    {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        $value = trim($value);

        $formats = ['d.m.Y', 'd/m/Y', 'd-m-Y', 'Y-m-d'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function collection(Collection $rows)
    {
        $companyId = auth()->user()->company_id;

        foreach ($rows as $row) {
            $plate = trim($row['plaka'] ?? '');

            if (empty($plate)) {
                continue;
            }

            $plate = strtoupper(str_replace(' ', '', $plate));

            // Araç bul
            $vehicle = Vehicle::where('company_id', $companyId)
                              ->where('plate', $plate)
                              ->first();

            if (!$vehicle) {
                continue; // Araç yoksa atla
            }

            $liters = !empty($row['litre']) ? (float) str_replace(',', '.', $row['litre']) : 0;
            $pricePerLiter = !empty($row['litre_fiyati']) ? (float) str_replace(',', '.', $row['litre_fiyati']) : 0;

            $stationName = trim($row['istasyon'] ?? '') ?: null;
            $stationId = null;

            // İstasyon adı verilmişse eşleştirmeye çalış
            if ($stationName) {
                $station = FuelStation::where('company_id', $companyId)
                    ->where('name', 'LIKE', '%' . $stationName . '%')
                    ->first();
                
                if ($station) {
                    $stationId = $station->id;
                    $stationName = $station->name;
                }
            }

            $pricing = $this->calculatePricing($liters, $pricePerLiter, $stationId);

            Fuel::create([
                'company_id'       => $companyId,
                'vehicle_id'       => $vehicle->id,
                'fuel_station_id'  => $stationId,
                'station_name'     => $stationName ?: 'Bilinmeyen İstasyon',
                'fuel_type'        => trim($row['yakit_turu'] ?? '') ?: 'Dizel',
                'date'             => $this->parseDate($row['tarih'] ?? null) ?: now()->format('Y-m-d'),
                'liters'           => $liters,
                'price_per_liter'  => $pricePerLiter,
                'gross_total_cost' => $pricing['gross_total_cost'],
                'vat_rate'         => $pricing['vat_rate'],
                'vat_amount'       => $pricing['vat_amount'],
                'net_cost'         => $pricing['net_cost'],
                'discount_amount'  => $pricing['discount_amount'],
                'total_cost'       => $pricing['total_cost'],
                'km'               => !empty($row['km']) ? (int) str_replace(['.', ','], '', $row['km']) : null,
                'notes'            => trim($row['notlar'] ?? '') ?: null,
            ]);
        }
    }

    protected function calculatePricing(float $liters, float $pricePerLiter, ?int $stationId = null): array
    {
        $vatRate = 0;
        $discountType = null;
        $discountValue = 0;

        if ($stationId) {
            $station = FuelStation::find($stationId);
            if ($station) {
                $vatRate = (float) $station->vat_rate;
                $discountType = $station->discount_type;
                $discountValue = (float) $station->discount_value;
            }
        }

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

        $kdvDahilTotal = $kdvDahilGross - $kdvDahilDiscount;
        $vatMultiplier = 1 + ($vatRate / 100);

        $kdvHaricGross = round($kdvDahilGross / $vatMultiplier, 2);
        $kdvHaricTotal = round($kdvDahilTotal / $vatMultiplier, 2);
        $kdvHaricDiscount = round($kdvDahilDiscount / $vatMultiplier, 2);
        $vatAmount = round($kdvDahilTotal - $kdvHaricTotal, 2);

        return [
            'gross_total_cost' => $kdvHaricGross,
            'vat_rate'         => $vatRate,
            'vat_amount'       => $vatAmount,
            'net_cost'         => $kdvHaricGross,
            'discount_amount'  => $kdvHaricDiscount,
            'total_cost'       => $kdvDahilTotal,
        ];
    }
}