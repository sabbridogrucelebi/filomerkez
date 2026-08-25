<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
use App\Models\Fleet\VehicleLocation;

$locations = VehicleLocation::withoutGlobalScope('company')
    ->orderBy('recorded_at', 'desc')
    ->take(5)
    ->get();

header('Content-Type: application/json');
echo json_encode($locations, JSON_PRETTY_PRINT);
