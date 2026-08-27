<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VehicleLocation;
use App\Models\LearnedStop;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyzeLearnedStops extends Command
{
    protected $signature = 'trips:analyze-stops {--days=7 : Number of days to analyze}';
    protected $description = 'Analyzes vehicle location history to learn and cluster common stops (Machine Learning Geofencing)';

    public function handle()
    {
        $days = $this->option('days');
        $this->info("Analyzing stops for the last {$days} days...");

        $startDate = Carbon::now()->subDays($days);
        $companies = Company::all();

        foreach ($companies as $company) {
            $this->info("Processing company: {$company->name}");
            
            // Get all stops (speed = 0) for the last X days for this company's vehicles
            // This is a simplified memory-efficient approach: fetch in chunks or run aggregation
            // Since this is a demo/prototype algorithm, we fetch points where speed = 0 and group them.
            
            $stops = DB::table('vehicle_locations')
                ->join('vehicles', 'vehicle_locations.vehicle_id', '=', 'vehicles.id')
                ->where('vehicles.company_id', $company->id)
                ->where('vehicle_locations.speed', 0)
                ->where('vehicle_locations.recorded_at', '>=', $startDate)
                ->select('vehicle_locations.latitude', 'vehicle_locations.longitude', 'vehicle_locations.recorded_at')
                ->get();

            if ($stops->isEmpty()) {
                continue;
            }

            $clusters = [];
            $clusterRadiusKm = 0.05; // 50 meters
            $minStopsToLearn = 3; // En az 3 kez farklı zamanlarda (veya kayıtlarda) durulmuş olmalı

            foreach ($stops as $stop) {
                $lat = (float) $stop->latitude;
                $lng = (float) $stop->longitude;
                $matched = false;

                foreach ($clusters as &$cluster) {
                    $dist = $this->haversineGreatCircleDistance($cluster['lat'], $cluster['lng'], $lat, $lng);
                    if ($dist <= $clusterRadiusKm) {
                        $cluster['count']++;
                        $cluster['last_stopped'] = max($cluster['last_stopped'], $stop->recorded_at);
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $clusters[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'count' => 1,
                        'last_stopped' => $stop->recorded_at
                    ];
                }
            }

            $savedCount = 0;
            // Identify new clusters
            $newClusters = [];
            
            foreach ($clusters as &$cluster) {
                if ($cluster['count'] >= $minStopsToLearn) {
                    $existing = LearnedStop::where('company_id', $company->id)->get();
                    $isNew = true;
                    
                    foreach ($existing as $ext) {
                        $dist = $this->haversineGreatCircleDistance($ext->latitude, $ext->longitude, $cluster['lat'], $cluster['lng']);
                        if ($dist <= $clusterRadiusKm) {
                            $ext->stop_count += $cluster['count'];
                            $ext->last_stopped_at = max($ext->last_stopped_at, $cluster['last_stopped']);
                            $ext->save();
                            $isNew = false;
                            $savedCount++;
                            break;
                        }
                    }
                    if ($isNew) {
                        $newClusters[] = $cluster;
                    }
                }
            }

            // Bulk check traffic lights for new clusters (ONE HTTP REQUEST)
            if (count($newClusters) > 0) {
                $trafficLights = $this->bulkCheckTrafficLights($newClusters);
                
                foreach ($newClusters as $cluster) {
                    $isTrafficLight = false;
                    foreach ($trafficLights as $tl) {
                        if ($this->haversineGreatCircleDistance($cluster['lat'], $cluster['lng'], $tl['lat'], $tl['lng']) <= 0.15) {
                            $isTrafficLight = true;
                            break;
                        }
                    }
                    
                    LearnedStop::create([
                        'company_id' => $company->id,
                        'latitude' => $cluster['lat'],
                        'longitude' => $cluster['lng'],
                        'radius_meters' => 50,
                        'stop_count' => $cluster['count'],
                        'last_stopped_at' => $cluster['last_stopped'],
                        'is_traffic_light' => $isTrafficLight
                    ]);
                    $savedCount++;
                }
            }
            
            $this->info("Saved/Updated {$savedCount} learned stops for company {$company->name}");
        }

        $this->info('Stop analysis complete!');
    }

    private function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * $earthRadius;
    }

    private function bulkCheckTrafficLights($clusters)
    {
        $trafficLights = [];
        try {
            $query = "[out:json];(";
            foreach ($clusters as $c) {
                $query .= "node(around:150,{$c['lat']},{$c['lng']})[\"highway\"=\"traffic_signals\"];";
            }
            $query .= ");out;";

            $client = new \GuzzleHttp\Client();
            $url = "https://overpass-api.de/api/interpreter";
            
            $response = $client->post($url, [
                'form_params' => ['data' => $query],
                'timeout' => 15, // Maksimum 15 saniye bekle
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['elements'])) {
                foreach ($data['elements'] as $el) {
                    $trafficLights[] = [
                        'lat' => $el['lat'],
                        'lng' => $el['lon']
                    ];
                }
            }
        } catch (\Exception $e) {
            // Sessizce geç
        }
        
        return $trafficLights;
    }
}
