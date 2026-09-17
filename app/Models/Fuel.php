<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Fleet\Vehicle;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Concerns\LogsActivity;

class Fuel extends Model
{
    use BelongsToCompany, LogsActivity;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'fuel_station_id',
        'station_name',
        'fuel_type',
        'date',
        'liters',
        'price_per_liter',
        'gross_total_cost',
        'vat_rate',
        'vat_amount',
        'net_cost',
        'discount_amount',
        'total_cost',
        'payment_status',
        'paid_amount',
        'km',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'liters' => 'decimal:2',
        'price_per_liter' => 'decimal:2',
        'gross_total_cost' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'net_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'fuel_station_id');
    }
}