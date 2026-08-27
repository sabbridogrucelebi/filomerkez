<?php

namespace App\Models\Fleet;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleReportSummary extends Model
{
    use BelongsToCompany;

    protected $table = 'vehicle_report_summaries';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'report_date',
        'eco_score',
        'harsh_braking_count',
        'rapid_acceleration_count',
        'sharp_turn_count',
        'speed_violation_count',
        'idle_minutes',
        'idle_fuel_loss_tl',
        'active_minutes',
        'total_distance_km',
        'active_capacity_percent',
        'route_deviation_percent',
        'brake_pad_wear_percent',
    ];

    protected $casts = [
        'report_date' => 'date',
        'idle_fuel_loss_tl' => 'float',
        'active_capacity_percent' => 'float',
        'route_deviation_percent' => 'float',
        'brake_pad_wear_percent' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
