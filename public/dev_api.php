<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture()); // Boot the framework properly

$user = \App\Models\User::first();
if($user) {
    auth()->login($user);
}

$controller = new \App\Http\Controllers\Api\V1\VehicleTrackingApiController();
$response = $controller->live(request());
$response->send();
