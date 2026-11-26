<?php

namespace App\Models\Fleet;

use App\Models\Fuel;
use App\Models\Company;
use App\Models\Document;
use App\Models\TrafficPenalty;
use Illuminate\Database\Eloquent\Builder;
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
        'device_imei',
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

    public function scopeOrderByCPlateNumber(Builder $query): Builder
    {
        $plate = "UPPER(REPLACE(vehicles.plate, ' ', ''))";
        $number = "SUBSTR($plate, 4)";
        $pattern = "'^[0-9]{2}C[0-9]+$'";
        $isCPlate = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "$plate GLOB '[0-9][0-9]C[0-9]*' AND $number NOT GLOB '*[^0-9]*'",
            'pgsql' => "$plate ~ $pattern",
            default => "$plate REGEXP $pattern",
        };

        return $query->orderByRaw("CASE WHEN $isCPlate THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN $isCPlate THEN CAST($number AS DECIMAL(20,0)) END")
            ->orderBy('vehicles.plate')
            ->orderBy('vehicles.id');
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

    public function kaskoDocuments(): MorphMany
    {
        return $this->documents()->where('document_type', 'Kasko')
            ->orderByRaw('COALESCE(end_date, created_at) DESC')->orderByDesc('id');
    }

    public function immDocuments(): MorphMany
    {
        return $this->documents()->whereIn('document_type', ['İMM Poliçesi', 'İMM POLİÇESİ'])
            ->orderByRaw('COALESCE(end_date, created_at) DESC')->orderByDesc('id');
    }

    public function kaskoExpiryDate(): ?\Carbon\Carbon
    {
        return $this->kaskoDocuments->first()?->end_date ?? $this->kasko_end_date;
    }

    /**
     * Muayene / Sigorta yaklaşan uyarıları (KPI kartları, liste filtresi, mobil KPI)
     * Araç detay sayfasındaki "Kritik Takvim" ile birebir aynı mantık:
     *  - İlgili türdeki (arşiv dahil) EN GÜNCEL bitiş tarihli belge esas alınır.
     *  - Belge yoksa araç kartındaki tarih alanına düşülür.
     *  - Etkin tarih bugün + ALERT_DAYS içinde (veya geçmişte) ise uyarı verilir.
     */
    public const ALERT_DAYS = [
        'inspection' => 30,
        'insurance'  => 30,
    ];

    public const ALERT_DOC_TYPES = [
        'inspection' => ['Muayene', 'Muayene Raporu'],
        'insurance'  => ['Sigorta', 'Sigorta Poliçesi'],
    ];

    public const ALERT_FALLBACK_FIELDS = [
        'inspection' => 'inspection_date',
        'insurance'  => 'insurance_end_date',
    ];

    /** Bitiş tarihi olan tüm belgeler (aktif + arşiv). */
    public function alertDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->whereNotNull('end_date');
    }

    /** Verilen tür için etkin bitiş tarihi (inspection | insurance). */
    public function alertDate(string $kind): ?\Carbon\Carbon
    {
        $types = self::ALERT_DOC_TYPES[$kind] ?? [];
        $field = self::ALERT_FALLBACK_FIELDS[$kind] ?? null;

        $latestDoc = $this->alertDocuments
            ->whereIn('document_type', $types)
            ->filter(fn ($d) => $d->end_date !== null)
            ->sortByDesc(fn ($d) => $d->end_date->timestamp)
            ->first();

        if ($latestDoc) {
            return $latestDoc->end_date->copy()->startOfDay();
        }

        if ($field && $this->{$field}) {
            return \Carbon\Carbon::parse($this->{$field})->startOfDay();
        }

        return null;
    }

    /** Uyarı penceresine giren (veya süresi geçmiş) mi? */
    public function hasUpcomingAlert(string $kind): bool
    {
        $date = $this->alertDate($kind);
        if (!$date) {
            return false;
        }

        return $date->lte(now()->startOfDay()->addDays(self::ALERT_DAYS[$kind] ?? 30));
    }

    /**
     * Uyarı verilmesi gereken aktif araç ID'leri.
     * BelongsToCompany global scope'u korunur; ayrıca $companyId verilirse ek filtre uygulanır.
     */
    public static function upcomingAlertIds(string $kind, ?int $companyId = null): array
    {
        $field = self::ALERT_FALLBACK_FIELDS[$kind];
        $types = self::ALERT_DOC_TYPES[$kind];

        return static::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('vehicle_type')->orWhere('vehicle_type', '!=', 'TRACKING_ONLY');
            })
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with(['alertDocuments' => fn ($q) => $q->whereIn('document_type', $types)])
            ->get(['id', 'company_id', $field])
            ->filter(fn (Vehicle $v) => $v->hasUpcomingAlert($kind))
            ->pluck('id')
            ->values()
            ->all();
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

    private static function latestCompletedMaintenanceKm(string $type): Builder
    {
        return VehicleMaintenance::query()->select('km')
            ->where('maintenance_type', $type)->where('status', 'completed')
            ->orderByDesc('service_date')->orderByDesc('id')->limit(1);
    }

    public function scopeWithMaintenanceReadings(Builder $query): Builder
    {
        return $query->with('maintenanceSetting')
            ->withMax('fuels as max_fuel_km', 'km')
            ->withMax('maintenances as max_maintenance_km', 'km')
            ->addSelect([
                'latest_oil_km' => self::latestCompletedMaintenanceKm('YAĞ BAKIMI')->whereColumn('vehicle_id', 'vehicles.id'),
                'latest_lube_km' => self::latestCompletedMaintenanceKm('ALT YAĞLAMA')->whereColumn('vehicle_id', 'vehicles.id'),
            ]);
    }

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
        $maxFuelKm = array_key_exists('max_fuel_km', $this->attributes)
            ? $this->max_fuel_km : $this->fuels()->max('km');
        $maxMaintenanceKm = array_key_exists('max_maintenance_km', $this->attributes)
            ? $this->max_maintenance_km : $this->maintenances()->max('km');
        $currentKm = max((int)$this->current_km, (int)$maxFuelKm, (int)$maxMaintenanceKm);

        // Yağ Değişimi Hesaplama
        $oilInterval = $setting->oil_change_interval_km;
        $hasOilSetting = !empty($oilInterval);
        
        $oilRemaining = null;
        $oilPercent = 0;
        
        if ($hasOilSetting) {
            $lastOilKm = array_key_exists('latest_oil_km', $this->attributes)
                ? $this->latest_oil_km : self::latestCompletedMaintenanceKm('YAĞ BAKIMI')->where('vehicle_id', $this->id)->value('km');
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
        $lubeInterval = $setting->under_lubrication_interval_km;
        $hasLubeSetting = !empty($lubeInterval);
        
        $lubeRemaining = null;
        $lubePercent = 0;
        
        if ($hasLubeSetting) {
            $lastLubeKm = array_key_exists('latest_lube_km', $this->attributes)
                ? $this->latest_lube_km : self::latestCompletedMaintenanceKm('ALT YAĞLAMA')->where('vehicle_id', $this->id)->value('km');
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
