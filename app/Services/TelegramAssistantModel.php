<?php

namespace App\Services;

use Illuminate\Support\Str;

/** A local nearest-neighbour intent model trained on representative Turkish fleet questions. */
final class TelegramAssistantModel
{
    private const EXAMPLES = [
        'date' => [
            'upcoming' => ['vadesi yaklasan kayitlari listele', 'yenileme takvimini onume koy', 'onumuzdeki hafta yenilenecekler',
                'hangi araclara bu hafta randevu almaliyiz', 'yenileme icin hazirlanmam gereken araclari getir'],
            'nearest' => ['sirada ilk hangi arac var', 'hangisinin vadesi digerlerinden once', 'oncelikli yenilenecek araci belirle',
                'ilk once hangisini halletmem gerekiyor', 'randevu icin ilk sirada hangi plaka var'],
            'expired' => ['gecikmeye dusen kayitlari goster', 'artik gecerliligi kalmamis araclar', 'yenilemesi gec kalmis plakalar',
                'vadesini kacirdigimiz araclari bul', 'gecerliligi sona ermis kayitlar'],
            'missing' => ['evragi bulunmayan araclar', 'kayit eksigi olan plakalar', 'poliçe tarihi bos gecilen araclari bul',
                'yenileme tarihi girilmemis araclar', 'hic belge yuklenmemis plakalar'],
        ],
        'maintenance' => [
            'upcoming' => ['servise alinacak araclari goster', 'atolye plani icin siradaki araclari listele', 'periyodik servis yaklasan araclar',
                'hangi araclari atolyeye hazirlamaliyiz', 'yag degisimi yaklasanlar'],
            'nearest' => ['ilk servise gidecek araci bul', 'en acil servis ihtiyaci hangisinde', 'atolyeye ilk hangisini alalim',
                'servis icin oncelikli plaka hangisi'],
            'expired' => ['servisi aksamis araclar', 'periyodik bakimi zamaninda yapilmayan plakalar', 'bakim kilometresini asmis olanlar',
                'servis randevusu kacirilmis olanlar', 'atolyeye gec kalmis araclar'],
        ],
        'fuel' => [
            'high' => ['normalin disinda tuketim gosteren araclar', 'mazot verimi kotuye giden plakalar', 'verim alt sinirini asan kayitlar',
                'yakitta alarm veren araclar', 'son hafta limitin disina cikanlar'],
            'highest' => ['en savurgan araci bul', 'hangi arac depoyu digerlerinden hizli bosaltiyor', 'tuketimde bası ceken plaka',
                'en dusuk kilometre litre verimi hangi aracta', 'mazotu en hizli bitiren arac'],
        ],
    ];

    private const STOP_WORDS = ['arac', 'araclar', 'araclari', 'plaka', 'icin', 'bir', 'mi', 'mu', 'ne', 'bana',
        'kasko', 'imm', 'sigorta', 'muayene', 'vize', 'egzoz', 'eksoz', 'yakit', 'bakim'];

    public function interpret(string $kind, string $question): ?array
    {
        $query = $this->features($question);
        if ($query === []) {
            return null;
        }

        $scores = [];
        foreach (self::EXAMPLES[$kind] as $intent => $examples) {
            $scores[$intent] = max(array_map(fn (string $example) => $this->similarity($query, $this->features($example)), $examples));
        }
        arsort($scores);
        $intent = array_key_first($scores);
        $values = array_values($scores);
        if ($values[0] < 0.30 || $values[0] - ($values[1] ?? 0.0) < 0.065) {
            return null;
        }

        return ['intent' => $intent];
    }

    private function features(string $text): array
    {
        $normalized = Str::lower(Str::ascii(mb_substr($text, 0, 300), 'tr'));
        $tokens = preg_split('/[^a-z]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $features = [];
        $previous = null;
        foreach ($tokens as $token) {
            if (strlen($token) < 2 || in_array($token, self::STOP_WORDS, true)) {
                continue;
            }
            $features['w:' . $token] = 2.5;
            $prefix = substr($token, 0, 4);
            if (strlen($prefix) === 4) {
                $features['p:' . $prefix] = 2.0;
            }
            if ($previous !== null) {
                $features['b:' . $previous . ':' . $prefix] = 2.0;
            }
            for ($i = 0; $i < strlen($token) - 2; $i++) {
                $features['c:' . substr($token, $i, 3)] = 0.3;
            }
            $previous = $prefix;
        }

        return $features;
    }

    private function similarity(array $left, array $right): float
    {
        $overlap = array_sum(array_intersect_key($left, $right));
        $length = sqrt(array_sum($left) * array_sum($right));

        return $length > 0 ? $overlap / $length : 0.0;
    }
}
