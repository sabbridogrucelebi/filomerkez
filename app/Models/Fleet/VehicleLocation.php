<?php

namespace App\Models\Fleet;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleLocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'imei',
        'latitude',
        'longitude',
        'speed',
        'course',
        'status',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'speed' => 'float',
        'course' => 'integer',
        'status' => 'array',
        'recorded_at' => 'datetime',
    ];

    /**
     * The company this location belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The vehicle this location belongs to.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
