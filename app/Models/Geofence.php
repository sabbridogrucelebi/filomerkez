<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Fleet\Vehicle;

class Geofence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'color',
        'coordinates',
        'radius',
        'is_active',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'radius' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function vehicles()
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_geofences')->withTimestamps();
    }

    /**
     * Bölge tipinin Türkçe karşılığı
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'restricted' => 'Yasak Bölge',
            'safe' => 'Güvenli Bölge',
            default => $this->type,
        };
    }
}
