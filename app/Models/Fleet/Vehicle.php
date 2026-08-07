<?php

namespace App\Models\Fleet;

use App\Models\Fuel;
use App\Models\Company;
use App\Models\Document;
use App\Models\TrafficPenalty;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\VehicleMaintenance;
use App\Models\VehicleMaintenanceSetting;
use Illuminate\Support\Str;

use App\Models\Concerns\LogsActivity;

use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToCompany, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'plate',
        'brand',
        'model',
        'vehicle_type',
        'vehicle_package',
        'model_year',
        'registration_date',
        'seat_count',
        'gear_type',
        'fuel_type',
        'color',
        'other_color',
        'engine_no',
        'chassis_no',
        'license_serial_no',
        'license_owner',
        'owner_tax_or_tc_no',
        'inspection_date',
        'exhaust_date',
        'insurance_end_date',
        'kasko_end_date',
        'is_active',
        'current_km',
        'status',
        'notes',
        'public_image_upload_token',
        'min_km_per_liter',
        'max_km_per_liter',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'inspection_date' => 'date',
        'exhaust_date' => 'date',
        'insurance_end_date' => 'date',
        'kasko_end_date' => 'date',
        'is_active' => 'boolean',
        'current_km' => 'integer',
        'min_km_per_liter' => 'float',
        'max_km_per_liter' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (!$vehicle->public_image_upload_token) {
                $vehicle->public_image_upload_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function fuels(): HasMany
    {
        return $this->hasMany(Fuel::class)->orderByDesc('date');
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order')->orderByDesc('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class)
            ->orderByDesc('service_date')
            ->orderByDesc('id');
    }

    public function maintenanceSetting(): HasOne
    {
        return $this->hasOne(VehicleMaintenanceSetting::class);
    }

    public function trafficPenalties(): HasMany
    {
        return $this->hasMany(TrafficPenalty::class)
            ->orderByDesc('penalty_date')
            ->orderByDesc('id');
    }

    /**
     * Akıllı Bakım Tahminleme Metotları
     */

    public function getMaintenanceStatusAttribute(): array
    {
        $setting = $this->maintenanceSetting;
        if (!$setting) {
            return [
                'has_setting' => false,
                'has_oil_setting' => false,
                'has_lube_setting' => false,
                'oil_remaining' => null,
                'lube_remaining' => null,
            ];
        }

        // Güncel KM'yi en yüksek değere göre bul (Araç, Yakıt, Bakım)
        $maxFuelKm = \App\Models\Fuel::where('vehicle_id', $this->id)->max('km') ?: 0;
        $maxMaintenanceKm = $this->maintenances()->max('km') ?: 0;
        $currentKm = max((int)$this->current_km, (int)$maxFuelKm, (int)$maxMaintenanceKm);

        // Yağ Değişimi Hesaplama
        $lastOilChange = $this->maintenances()
            ->where('maintenance_type', 'YAĞ BAKIMI')
            ->where('status', 'completed')
            ->orderByDesc('service_date')
            ->orderByDesc('km')
            ->first();

        $oilInterval = $setting->oil_change_interval_km;
        $hasOilSetting = !empty($oilInterval);
        
        $oilRemaining = null;
        $oilPercent = 0;
        
        if ($hasOilSetting) {
            $lastOilKm = $lastOilChange ? $lastOilChange->km : 0;
            // Eğer daha önce hiç yağ bakımı kaydı yoksa hesaplama yanlış çıkmasın diye
            if ($lastOilKm > 0) {
                $oilRemaining = ($lastOilKm + $oilInterval) - $currentKm;
                $oilPercent = max(0, min(100, ($oilRemaining / $oilInterval) * 100));
            } else {
                // Kayıt yoksa kalan km interval kadar başlasın veya 0 olsun.
                // Biz sadece uyarı verebiliriz veya interval kadar kaldı diyebiliriz.
                $oilRemaining = $oilInterval;
                $oilPercent = 100;
            }
        }

        // Alt Yağlama Hesaplama
        $lastLubeChange = $this->maintenances()
            ->where('maintenance_type', 'ALT YAĞLAMA')
            ->where('status', 'completed')
            ->orderByDesc('service_date')
            ->orderByDesc('km')
            ->first();

        $lubeInterval = $setting->under_lubrication_interval_km;
        $hasLubeSetting = !empty($lubeInterval);
        
        $lubeRemaining = null;
        $lubePercent = 0;
        
        if ($hasLubeSetting) {
            $lastLubeKm = $lastLubeChange ? $lastLubeChange->km : 0;
            if ($lastLubeKm > 0) {
                $lubeRemaining = ($lastLubeKm + $lubeInterval) - $currentKm;
                $lubePercent = max(0, min(100, ($lubeRemaining / $lubeInterval) * 100));
            } else {
                $lubeRemaining = $lubeInterval;
                $lubePercent = 100;
            }
        }

        return [
            'has_setting' => $hasOilSetting || $hasLubeSetting,
            'has_oil_setting' => $hasOilSetting,
            'oil_remaining' => $oilRemaining,
            'oil_percent' => $oilPercent,
            'has_lube_setting' => $hasLubeSetting,
            'lube_remaining' => $lubeRemaining,
            'lube_percent' => $lubePercent,
            'current_km' => $currentKm,
        ];
    }
}