<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FuelStation;
use App\Models\Fuel;
use App\Models\FuelStationPayment;
use Carbon\Carbon;

$station = FuelStation::where('name', 'like', '%Lukoil%')->first();
if(!$station) die("Station not found\n");

echo "Station: " . $station->name . "\n";
echo "Total Debt (Overall): " . ($station->fuels()->sum('total_cost') - $station->payments()->sum('amount')) . "\n\n";

$fuels = Fuel::where('fuel_station_id', $station->id)->get();
$payments = FuelStationPayment::where('fuel_station_id', $station->id)->get();

$monthlyData = [];

foreach($fuels as $fuel) {
    $month = Carbon::parse($fuel->date)->format('Y-m');
    if(!isset($monthlyData[$month])) {
        $monthlyData[$month] = ['fuel' => 0, 'payment' => 0];
    }
    $monthlyData[$month]['fuel'] += $fuel->total_cost;
}

foreach($payments as $payment) {
    $month = Carbon::parse($payment->date)->format('Y-m');
    if(!isset($monthlyData[$month])) {
        $monthlyData[$month] = ['fuel' => 0, 'payment' => 0];
    }
    $monthlyData[$month]['payment'] += $payment->amount;
}

ksort($monthlyData);

printf("%-10s | %-15s | %-15s | %-15s\n", "Month", "Fuel Total", "Payment Total", "Difference");
echo str_repeat("-", 65) . "\n";
foreach($monthlyData as $month => $data) {
    $diff = $data['fuel'] - $data['payment'];
    printf("%-10s | %15.2f | %15.2f | %15.2f\n", $month, $data['fuel'], $data['payment'], $diff);
}
