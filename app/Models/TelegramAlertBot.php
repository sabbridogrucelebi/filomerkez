<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TelegramAlertBot extends Model
{
    use BelongsToCompany;

    public const CHANNELS = [
        'imm' => ['label' => 'İMM', 'icon' => '☂️', 'description' => 'İhtiyari mali mesuliyet poliçesi bitişi', 'kind' => 'date'],
        'insurance' => ['label' => 'Trafik sigortası', 'icon' => '📑', 'description' => 'Zorunlu trafik sigortası bitişi', 'kind' => 'date'],
        'inspection' => ['label' => 'Vize / Muayene', 'icon' => '🔍', 'description' => 'Araç muayenesi geçerlilik tarihi', 'kind' => 'date'],
        'exhaust' => ['label' => 'Egzoz', 'icon' => '💨', 'description' => 'Egzoz emisyon ölçümü bitişi', 'kind' => 'date'],
        'maintenance' => ['label' => 'Araç bakımı', 'icon' => '🛠️', 'description' => 'Yağ, alt yağlama ve planlanan bakım', 'kind' => 'maintenance'],
        'fuel' => ['label' => 'Aşırı yakıt', 'icon' => '⛽', 'description' => 'Araçtaki minimum km/litre sınırının altındaki alımlar', 'kind' => 'fuel'],
    ];

    protected $fillable = ['company_id', 'channel', 'is_enabled', 'bot_token', 'chat_id', 'reminder_days', 'maintenance_km', 'message_template'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'bot_token' => 'encrypted',
        'chat_id' => 'encrypted',
        'reminder_days' => 'array',
        'maintenance_km' => 'integer',
    ];

    protected $hidden = ['bot_token', 'chat_id'];

    public function hasCredentials(): bool
    {
        return filled($this->bot_token) && filled($this->chat_id);
    }
}
