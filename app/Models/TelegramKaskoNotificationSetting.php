<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TelegramKaskoNotificationSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'is_enabled',
        'bot_token',
        'chat_id',
        'reminder_days',
        'message_template',
        'last_telegram_update_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'bot_token' => 'encrypted',
        'chat_id' => 'encrypted',
        'reminder_days' => 'array',
        'last_telegram_update_id' => 'integer',
    ];

    protected $hidden = [
        'bot_token',
        'chat_id',
    ];

    public static function defaultMessageTemplate(): string
    {
        return <<<'TEXT'
🚨 <b>KASKO BİTİŞ UYARISI</b>

Sayın <b>{{company_name}}</b> yetkilisi,

Aşağıdaki aracın kasko poliçesi {{kasko_status}}

🚘 <b>Plaka:</b> {{plate}}
🏷️ <b>Marka / Model:</b> {{brand_model}}
📅 <b>Model Yılı:</b> {{model_year}}
🔢 <b>Seri No:</b> {{license_serial_no}}
🧾 <b>Şase No:</b> {{chassis_no}}
🛡️ <b>Kasko Bitiş Tarihi:</b> {{kasko_end_date}}

⏳ Poliçe yenileme işlemlerini zamanında yaparak
aracınızın güvence sürekliliğini koruyun.

<i>Filo Merkez | Filo Takip Sistemleri</i>
TEXT;
    }

    public function hasCredentials(): bool
    {
        return filled($this->bot_token) && filled($this->chat_id);
    }
}
