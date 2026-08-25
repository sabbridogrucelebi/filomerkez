<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleTelemetryApiController extends Controller
{
    /**
     * Store telemetry data coming from the VPS TCP Bridge.
     */
    public function store(Request $request)
    {
        // Simple authentication based on a secret key (can be improved later)
        $secret = $request->header('X-Telemetry-Secret');
        if ($secret !== 'filo-telemetry-2026-secret') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'imei' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'speed' => 'nullable|numeric',
            'course' => 'nullable|integer',
            'status' => 'nullable|array',
            'recorded_at' => 'nullable|date',
        ]);

        $imei = trim($validated['imei']);

        // Find the vehicle that this IMEI belongs to (bypassing global scopes since request is unauthenticated)
        $vehicle = Vehicle::withoutGlobalScope('company')->where('device_imei', $imei)->first();

        if (!$vehicle) {
            Log::warning("Telemetry received for unknown IMEI: '{$imei}'");
            return response()->json(['error' => 'Vehicle not found for given IMEI'], 404);
        }

        // Store the location
        $location = VehicleLocation::create([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'imei' => $imei,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'speed' => $validated['speed'] ?? 0,
            'course' => $validated['course'] ?? 0,
            'status' => $validated['status'] ?? null,
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telemetry saved',
            'location_id' => $location->id
        ]);
    }
}
