<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class WorkSchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'schedule',
        'alert_outside_hours',
        'is_active',
    ];

    protected $casts = [
        'schedule' => 'array',
        'alert_outside_hours' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Belirli bir günün mesai saatlerini döndür
     */
    public function getScheduleForDay(string $day): ?array
    {
        return $this->schedule[$day] ?? null;
    }
}
