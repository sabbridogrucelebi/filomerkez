<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TelegramAlertDelivery extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'bot_id', 'vehicle_id', 'event_key'];
}
