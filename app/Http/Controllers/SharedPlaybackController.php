<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SharedPlayback;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SharedPlaybackController extends Controller
{
    /**
     * Misafirler için paylaşılan kaydı gösterir (Web Görünümü)
     */
    public function showGuestView($uuid)
    {
        $shared = SharedPlayback::where('uuid', $uuid)->with('vehicle')->firstOrFail();

        // Süresi geçmişse hata ver
        if ($shared->expires_at && $shared->expires_at->isPast()) {
            abort(403, 'Bu paylaşım linkinin süresi dolmuştur.');
        }

        // Geçmiş veriyi çek (Mevcut mantığı kullanabilmek için geçici olarak giriş yapıyoruz)
        $adminUser = \App\Models\User::where('company_id', $shared->company_id)->first();
        if ($adminUser) {
            \Illuminate\Support\Facades\Auth::login($adminUser);
        }

        $trackingController = app(\App\Http\Controllers\LiveTrackingController::class);
        $request = new \Illuminate\Http\Request([
            'vehicle_id' => $shared->vehicle_id,
            'start_date' => $shared->start_date,
            'end_date'   => $shared->end_date,
        ]);
        
        $historyResponse = $trackingController->historyData($request);
        $historyDataArray = json_decode($historyResponse->getContent(), true);

        if ($adminUser) {
            \Illuminate\Support\Facades\Auth::logout();
        }

        $historyData = $historyDataArray['history'] ?? [];
        $tripData = $historyDataArray['trips'] ?? [];
        
        $isGuest = true;
        $vehicles = [];

        return view('live-tracking.index', compact('shared', 'historyData', 'tripData', 'isGuest', 'vehicles'));
    }

    /**
     * Admin panelinden paylaşım linki oluşturur
     */
    public function generateLink(Request $request)
    {
        $request->validate([
            'vehicle_id'  => 'required|integer',
            'date_filter' => 'nullable|string',
            'start_date'  => 'nullable|string',
            'end_date'    => 'nullable|string',
            'duration'    => 'required|string',
        ]);

        $startDateStr = $request->start_date;
        $endDateStr = $request->end_date;

        if ($request->date_filter && $request->date_filter !== 'custom') {
            $nowLocal = Carbon::now()->addHours(3);
            switch ($request->date_filter) {
                case 'last_1_hour':
                    $startDateStr = Carbon::now()->subHour()->format('Y-m-d H:i:s');
                    $endDateStr = Carbon::now()->format('Y-m-d H:i:s');
                    break;
                case 'today':
                    $startDateStr = $nowLocal->copy()->startOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    $endDateStr = $nowLocal->copy()->endOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    break;
                case 'yesterday':
                    $startDateStr = $nowLocal->copy()->subDay()->startOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    $endDateStr = $nowLocal->copy()->subDay()->endOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    break;
                case 'last_3_hours':
                    $startDateStr = Carbon::now()->subHours(3)->format('Y-m-d H:i:s');
                    $endDateStr = Carbon::now()->format('Y-m-d H:i:s');
                    break;
                case 'last_3_days':
                    $startDateStr = $nowLocal->copy()->subDays(2)->startOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    $endDateStr = $nowLocal->copy()->endOfDay()->subHours(3)->format('Y-m-d H:i:s');
                    break;
            }
        }

        $expiresAt = null;
        if ($request->duration === '24h') {
            $expiresAt = Carbon::now()->addHours(24);
        } elseif ($request->duration === '7d') {
            $expiresAt = Carbon::now()->addDays(7);
        }

        $uuid = Str::random(8);

        $shared = SharedPlayback::create([
            'uuid' => $uuid,
            'company_id' => auth()->user()->company_id ?? 1,
            'vehicle_id' => $request->vehicle_id,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'success' => true,
            'link' => url('/share/v/' . $uuid)
        ]);
    }
}
