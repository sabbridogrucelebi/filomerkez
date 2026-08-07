<?php

namespace App\Models\Fleet;

use App\Models\Company;
use App\Models\Document;
use App\Models\DriverVehicleAssignment;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

use App\Models\Concerns\LogsActivity;

use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use BelongsToCompany, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'full_name',
        'tc_no',
        'phone',
        'email',
        'license_class',
        'src_type',
        'birth_date',
        'start_date',
        'leave_date',
        'leave_shift',
        'start_shift',
        'base_salary',
        'is_fixed_salary',
        'is_active',
        'approval_status',
        'address',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'start_date' => 'date',
        'leave_date' => 'date',
        'base_salary' => 'decimal:2',
        'is_fixed_salary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class)->orderByDesc('period_month');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /*
    |--------------------------------------------------------------------------
    | ARAÇ ZİMMET GEÇMİŞİ İLİŞKİLERİ
    |--------------------------------------------------------------------------
    */

    /**
     * Tüm araç zimmet geçmişi (en yeniden en eskiye).
     */
    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(DriverVehicleAssignment::class)->orderByDesc('assigned_at');
    }

    /**
     * Şu an aktif olan araç zimmeti (unassigned_at NULL olan).
     */
    public function currentVehicleAssignment(): HasOne
    {
        return $this->hasOne(DriverVehicleAssignment::class)
                    ->whereNull('unassigned_at')
                    ->latest('assigned_at');
    }

    /**
     * Belirli bir tarihte şoförün hangi araçta çalıştığını döndürür.
     * Zimmet geçmişi tablosundan bakar, bulamazsa mevcut vehicle_id'ye fall back eder.
     *
     * @return Vehicle|null
     */
    public function vehicleOnDate($date): ?Vehicle
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $assignment = DriverVehicleAssignment::withoutGlobalScopes()
            ->where('driver_id', $this->id)
            ->where('assigned_at', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('unassigned_at')
                  ->orWhere('unassigned_at', '>=', $dateStr);
            })
            ->first();

        if ($assignment) {
            return Vehicle::withoutGlobalScopes()->find($assignment->vehicle_id);
        }

        // Fallback: Zimmet geçmişi yoksa mevcut araç
        return $this->vehicle_id ? Vehicle::withoutGlobalScopes()->find($this->vehicle_id) : null;
    }

    /**
     * Belirli bir tarihte şoförün hangi araç ID'sine zimmetli olduğunu döndürür.
     * Zimmet geçmişi tablosundan bakar, bulamazsa mevcut vehicle_id'ye fall back eder.
     *
     * @return int|null
     */
    public function vehicleIdOnDate($date): ?int
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        $assignment = DriverVehicleAssignment::withoutGlobalScopes()
            ->where('driver_id', $this->id)
            ->where('assigned_at', '<=', $dateStr)
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('unassigned_at')
                  ->orWhere('unassigned_at', '>=', $dateStr);
            })
            ->first();

        if ($assignment) {
            return $assignment->vehicle_id;
        }

        // Fallback: Zimmet geçmişi yoksa mevcut araç
        return $this->vehicle_id;
    }

    /**
     * Belirli bir tarih aralığındaki tüm araç zimmetlerini döndürür.
     * Maaş hesaplaması için kullanılır — ay içinde birden fazla araç kullanılmışsa hepsi döner.
     *
     * @return \Illuminate\Database\Eloquent\Collection<DriverVehicleAssignment>
     */
    public function vehicleAssignmentsInRange($startDate, $endDate)
    {
        return DriverVehicleAssignment::withoutGlobalScopes()
            ->where('driver_id', $this->id)
            ->activeInRange($startDate, $endDate)
            ->orderBy('assigned_at')
            ->get();
    }
}