<?php

namespace App\Exceptions;

use RuntimeException;

final class TelegramConnectionProblem extends RuntimeException
{
    public function __construct(?int $curlCode, bool $beforeSend = false)
    {
        $message = match ($curlCode) {
            5, 6 => 'Sunucu Telegram adresini DNS üzerinden çözemedi. Hosting desteğinden api.telegram.org için DNS çözümlemesini kontrol etmesini isteyin.',
            7 => 'Sunucu Telegram’a ağ bağlantısı kuramadı. Hosting desteğinden api.telegram.org:443 çıkışını kontrol etmesini isteyin.',
            28 => $beforeSend
                ? 'Sunucu Telegram’a bağlanırken zaman aşımına uğradı. Hosting desteğinden api.telegram.org:443 çıkışını kontrol etmesini isteyin.'
                : 'Telegram yanıtı zamanında gelmedi. Tekrar göndermeden önce mesajın gruba ulaşıp ulaşmadığına bakın.',
            35, 51, 58, 59, 60, 77, 83, 90, 91 => 'Sunucu ile Telegram arasında güvenli HTTPS bağlantısı kurulamadı. Hosting desteğinden TLS ve CA sertifika ayarlarını kontrol etmesini isteyin.',
            52, 55, 56, 92 => 'Telegram bağlantısı gönderim sırasında kesildi. Tekrar göndermeden önce mesajın gruba ulaşıp ulaşmadığına bakın.',
            default => 'Sunucu Telegram gönderimini doğrulayamadı. Grubu kontrol edin. Hata tekrarlanırsa hosting desteğinden api.telegram.org:443 çıkışını kontrol etmesini isteyin.',
        };

        parent::__construct($curlCode ? "$message (cURL $curlCode)" : $message);
    }
}
