<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class SharedPlayback extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = [];
    
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Fleet\Vehicle::class);
    }
}
