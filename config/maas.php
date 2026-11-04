<?php

/*
 | Çelebi Maaş Sistemi entegrasyonu
 | Maaş sistemi araç listesini bu anahtarla, kullanıcı girişi olmadan (salt-okunur) çeker.
 | Canlı sunucuda .env içinden değiştirilebilir:
 |   MAAS_ENTEGRASYON_ANAHTARI=...
 |   MAAS_ENTEGRASYON_FIRMA_ID=2
 */
return [
    'anahtar'  => env('MAAS_ENTEGRASYON_ANAHTARI', 'ea467bb58ec217c9346258a79cda71015621543f1973824083d0db546033ddbf'),
    'firma_id' => (int) env('MAAS_ENTEGRASYON_FIRMA_ID', 2), // MEHMET ÇELEBİ TURİZM
];

