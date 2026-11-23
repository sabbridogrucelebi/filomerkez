<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Fleet\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramKaskoNotificationLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'kasko_end_date',
        'reminder_day',
        'telegram_message_id',
        'sent_at',
    ];

    protected $casts = [
        'kasko_end_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
