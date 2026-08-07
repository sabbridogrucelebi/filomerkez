<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class Holiday extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'date',
        'name',
        'is_half_day',
    ];

    protected $casts = [
        'date' => 'date',
        'is_half_day' => 'boolean',
    ];
}
