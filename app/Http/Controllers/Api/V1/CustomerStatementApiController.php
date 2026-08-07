<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Trip;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomerStatementApiController extends Controller
{
    /**
     * Müşteri Ekstresi (Hangi araç, hangi şoför, hangi gün kime gitmiş, ne kadara çalışmış)
     */
    public function statement(Request $request, $customerId)
    {
        $customer = Customer::where('company_id', auth()->user()->company_id)
            ->findOrFail($customerId);

        $period = $request->get('period', now()->format('Y-m'));
        [$year, $month] = explode('-', $period);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Müşterinin o aydaki tüm seferlerini getir (Araç, şoför ve güzergah detayıyla)
        $trips = Trip::with(['serviceRoute', 'vehicle', 'driver', 'morningDriver', 'eveningDriver'])
            ->whereHas('serviceRoute', function ($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            })
            ->whereBetween('trip_date', [$startDate, $endDate])
            ->orderBy('trip_date', 'asc')
            ->get();

        $statementData = [];
        $totalCost = 0;

        foreach ($trips as $trip) {
            $route = $trip->serviceRoute;
            
            // Eğer güzergah ücretli değilse (paid), bedel hesaplamayabiliriz. 
            // Ancak cari ekstresinde tutarı göstereceğiz.
            $morningFee = $route->morning_fee ?? 0;
            $eveningFee = $route->evening_fee ?? 0;

            if ($trip->trip_date->isSaturday() && $route->saturday_pricing) {
                $morningFee = $route->fallback_morning_fee ?? 0;
                $eveningFee = $route->fallback_evening_fee ?? 0;
            }

            if ($trip->trip_date->isSunday() && $route->sunday_pricing) {
                $morningFee = $route->fallback_morning_fee ?? 0;
                $eveningFee = $route->fallback_evening_fee ?? 0;
            }

            // Hangi şoförler gitti?
            $morningDriverName = $trip->morningDriver ? $trip->morningDriver->full_name : ($trip->driver ? $trip->driver->full_name : '-');
            $eveningDriverName = $trip->eveningDriver ? $trip->eveningDriver->full_name : ($trip->driver ? $trip->driver->full_name : '-');

            $tripTotal = 0;
            
            if ($route->service_type === 'both' || $route->service_type === 'morning') {
                $tripTotal += $morningFee;
            }
            if ($route->service_type === 'both' || $route->service_type === 'evening') {
                $tripTotal += $eveningFee;
            }

            $totalCost += $tripTotal;

            $statementData[] = [
                'id' => $trip->id,
                'date' => $trip->trip_date->format('d.m.Y'),
                'route_name' => $route->route_name,
                'vehicle_plate' => $trip->vehicle ? $trip->vehicle->plate : ($route->morningVehicle ? $route->morningVehicle->plate : '-'),
                'morning_driver' => $morningDriverName,
                'evening_driver' => $eveningDriverName,
                'service_type' => $route->service_type,
                'cost' => $tripTotal,
                'notes' => $trip->notes
            ];
        }

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'company_name' => $customer->company_name
            ],
            'period' => clone $startDate->locale('tr')->translatedFormat('F Y'),
            'total_cost' => $totalCost,
            'details' => $statementData
        ]);
    }
}
