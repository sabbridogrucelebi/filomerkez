<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\LogsActivity;

class Customer extends Model
{
    use BelongsToCompany, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_type',
        'company_name',
        'company_title',
        'authorized_person',
        'authorized_phone',
        'phone',
        'email',
        'address',
        'contract_start_date',
        'contract_end_date',
        'monthly_price',
        'vat_rate',
        'withholding_rate',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'monthly_price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CustomerContract::class)
            ->orderByDesc('year')
            ->orderByDesc('end_date')
            ->orderByDesc('id');
    }

    public function serviceRoutes(): HasMany
    {
        return $this->hasMany(CustomerServiceRoute::class)
            ->latest();
    }

    public function jointVentures(): HasMany
    {
        return $this->hasMany(CustomerJointVenture::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('user_type', 'customer_portal')
            ->latest();
    }
}