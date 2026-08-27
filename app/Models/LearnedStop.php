<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class LearnedStop extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'latitude',
        'longitude',
        'radius_meters',
        'stop_count',
        'last_stopped_at'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'radius_meters' => 'integer',
        'stop_count' => 'integer',
        'last_stopped_at' => 'datetime'
    ];
}
