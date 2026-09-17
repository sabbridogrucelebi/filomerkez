<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelStation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'legal_name',
        'address',
        'discount_type',
        'discount_value',
        'vat_rate',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fuels(): HasMany
    {
        return $this->hasMany(Fuel::class, 'fuel_station_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FuelStationPayment::class);
    }
}