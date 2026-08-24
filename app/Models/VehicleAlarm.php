<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Fleet\Vehicle;

class VehicleAlarm extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'alarm_type',
        'threshold_value',
        'is_active',
        'notify_email',
        'notify_sms',
        'notify_panel',
        'notes',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:2',
        'is_active' => 'boolean',
        'notify_email' => 'boolean',
        'notify_sms' => 'boolean',
        'notify_panel' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Alarm tipinin Türkçe karşılığı
     */
    public function getAlarmTypeLabelAttribute(): string
    {
        return match($this->alarm_type) {
            'speed' => 'Hız Alarmı',
            'stop' => 'Durak Alarmı',
            'ignition' => 'Kontak Alarmı',
            'geofence' => 'Bölge Alarmı',
            default => $this->alarm_type,
        };
    }
}
