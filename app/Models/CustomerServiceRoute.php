<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerServiceRoute extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'route_name',
        'service_type',
        'vehicle_type',
        'morning_vehicle_id',
        'evening_vehicle_id',
        'fee_type',
        'morning_fee',
        'evening_fee',
        'fallback_morning_fee',
        'fallback_evening_fee',
        'saturday_pricing',
        'sunday_pricing',
        'is_active',
    ];

    protected $casts = [
        'morning_fee'          => 'decimal:2',
        'evening_fee'          => 'decimal:2',
        'fallback_morning_fee' => 'decimal:2',
        'fallback_evening_fee' => 'decimal:2',
        'saturday_pricing'     => 'boolean',
        'sunday_pricing'       => 'boolean',
        'is_active'            => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function morningVehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Fleet\Vehicle::class, 'morning_vehicle_id');
    }

    public function eveningVehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Fleet\Vehicle::class, 'evening_vehicle_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPaid(): bool
    {
        return $this->fee_type === 'paid';
    }

    public function isBothService(): bool
    {
        return $this->service_type === 'both';
    }

    public function isMorningOnly(): bool
    {
        return $this->service_type === 'morning';
    }

    public function isEveningOnly(): bool
    {
        return $this->service_type === 'evening';
    }

    public function stops()
    {
        return $this->hasMany(CustomerRouteStop::class, 'customer_service_route_id')->orderBy('stop_order');
    }

    public function jointVenture()
    {
        return $this->belongsTo(CustomerJointVenture::class, 'joint_venture_id');
    }
}