<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\Fleet\VehicleLocation;

// Delete ALL old location records (test data included)
// Fresh real data will flow in from the GPS device within seconds
$deleted = VehicleLocation::withoutGlobalScope('company')->delete();

// Also clear all caches
Artisan::call('cache:clear');
Artisan::call('route:clear');
Artisan::call('view:clear');
Artisan::call('config:clear');

echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:100px;background:#0f172a;color:#10b981;'>";
echo "<h1>✅ TEMİZLİK TAMAMLANDI!</h1>";
echo "<p style='font-size:24px;'>Silinen test kayıt sayısı: <strong>{$deleted}</strong></p>";
echo "<p style='color:#94a3b8;'>Tüm önbellekler de temizlendi. GPS cihazı saniyeler içinde yeni gerçek verileri gönderecek.</p>";
echo "</body></html>";
