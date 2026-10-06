<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$fuels = \App\Models\Fuel::whereBetween('date', ['2026-09-01', '2026-09-30'])->get();

$summary = [
    'count' => $fuels->count(),
    'sum_liters' => $fuels->sum('liters'),
    'sum_gross' => $fuels->sum('gross_total_cost'),
    'sum_discount' => $fuels->sum('discount_amount'),
    'sum_vat' => $fuels->sum('vat_amount'),
    'sum_total' => $fuels->sum('total_cost'),
];

echo json_encode(['summary' => $summary, 'sample' => $fuels->take(5)]);
