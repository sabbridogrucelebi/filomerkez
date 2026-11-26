@extends('layouts.app')

@section('title', 'Yönetim Paneli')
@section('subtitle', 'Filo ve operasyon özeti')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('assets/fleet-dashboard.css') }}">
@endpush

@section('content')

@php
    $hour = now()->hour;
    $greeting = 'İyi Günler';
    if ($hour < 12) $greeting = 'Günaydın';
    elseif ($hour < 18) $greeting = 'Tünaydın';
    else $greeting = 'İyi Akşamlar';

    // Bakım Verileri
    $maintVehicles = \App\Models\Fleet\Vehicle::withMaintenanceReadings()
        ->where('company_id', auth()->user()->company_id)
        ->get();
    
    $maintAlerts = collect();
    $healthyCount = 0;
    
    foreach ($maintVehicles as $v) {
        $mStatus = $v->maintenance_status;
        $needsAttention = false;
        
        if ($mStatus['has_oil_setting'] && $mStatus['oil_remaining'] !== null && $mStatus['oil_remaining'] <= 200) {
            $maintAlerts->push([
                'vehicle' => $v,
                'type' => 'Yağ Değişimi',
                'remaining' => $mStatus['oil_remaining'],
                'critical' => $mStatus['oil_remaining'] < 0
            ]);
            $needsAttention = true;
        }
        
        if ($mStatus['has_lube_setting'] && $mStatus['lube_remaining'] !== null && $mStatus['lube_remaining'] <= 200) {
            $maintAlerts->push([
                'vehicle' => $v,
                'type' => 'Alt Yağlama',
                'remaining' => $mStatus['lube_remaining'],
                'critical' => $mStatus['lube_remaining'] < 0
            ]);
            $needsAttention = true;
        }
        
        if (!$needsAttention) {
            $healthyCount++;
        }
    }
    
    // Sort by remaining (most critical first)
    $maintAlerts = $maintAlerts->sortBy('remaining')->values();
@endphp

<div class="fleet-dashboard">
    <section class="fleet-hero" aria-labelledby="fleet-greeting">
        <div class="fleet-intro">
            <time datetime="{{ now()->toDateString() }}">{{ now()->locale('tr')->translatedFormat('d F Y, l') }}</time>
            <h2 id="fleet-greeting">{{ $greeting }}, <span>{{ explode(' ', auth()->user()->name)[0] }}</span></h2>
            @if(auth()->user()->hasPermission('trips.view'))
                <a class="fleet-action" href="{{ route('trips.create') }}"><span aria-hidden="true">+</span> Yeni sefer ekle</a>
            @endif
        </div>

        <div class="fleet-scene" aria-hidden="true">
            <img class="fleet-coach" src="{{ asset('assets/images/fleet-dashboard-coach.webp') }}"
                alt="" width="1000" height="667" fetchpriority="high" decoding="async">
        </div>

        <dl class="fleet-today" aria-label="Günlük operasyon">
            <div><dt>Bugünkü sefer</dt><dd>{{ $todayTrips }}</dd></div>
            <div><dt>Devam eden bakım</dt><dd>{{ $activeMaintenances }}</dd></div>
            <div><dt>Bekleyen bakım</dt><dd>{{ $waitingMaintenances }}</dd></div>
        </dl>
    </section>

    <dl class="fleet-summary" aria-label="Filo özeti">
        <div><dt>Toplam araç</dt><dd>{{ $vehicleCount }}</dd></div>
        <div><dt>Şoför</dt><dd>{{ $driverCount }}</dd></div>
        <div><dt>Müşteri</dt><dd>{{ $customerCount }}</dd></div>
        <div><dt>Aylık gelir</dt><dd>{{ number_format($monthlyIncome, 0, ',', '.') }} ₺</dd></div>
    </dl>

    <div class="fleet-columns">
        <div class="fleet-stack">
            <section class="fleet-panel" aria-labelledby="fleet-maintenance">
                <div class="fleet-panel-heading">
                    <div>
                        <h3 id="fleet-maintenance">Filo Bakım Sağlığı</h3>
                        <p class="fleet-detail">200 km içinde yaklaşan ve geciken bakımlar</p>
                    </div>
                    <div class="fleet-counts">
                        <span class="fleet-count">{{ $healthyCount }} uyarısız araç</span>
                        <span class="fleet-count">{{ $maintAlerts->count() }} bakım uyarısı</span>
                    </div>
                </div>

                <ul>
                    @forelse($maintAlerts as $alert)
                        <li class="fleet-record">
                            <div>
                                <a class="fleet-link" href="{{ route('vehicles.show', ['vehicle' => $alert['vehicle']->id, 'tab' => 'maintenances']) }}"
                                    aria-label="{{ $alert['vehicle']->plate }} bakım detayları">{{ $alert['vehicle']->plate }}</a>
                                <p class="fleet-detail">{{ $alert['type'] }}</p>
                            </div>
                            <b class="{{ $alert['critical'] ? 'fleet-overdue' : 'fleet-due' }}">
                                {{ number_format(abs($alert['remaining']), 0, ',', '.') }} km {{ $alert['critical'] ? 'gecikti' : 'kaldı' }}
                            </b>
                        </li>
                    @empty
                        <li class="fleet-empty">Bakım uyarısı yok.</li>
                    @endforelse
                </ul>
            </section>

            @if($fuelAnomalies->isNotEmpty())
                <section class="fleet-panel" aria-labelledby="fleet-fuel">
                    <div class="fleet-panel-heading">
                        <h3 id="fleet-fuel">Yakıt Tüketim Uyarıları</h3>
                        <span class="fleet-detail">{{ $fuelAnomalies->count() }} kayıt</span>
                    </div>
                    <ul>
                        @foreach($fuelAnomalies as $anomaly)
                            @php
                                $belowLimit = $anomaly->anomaly_type === 'low';
                                $limit = $belowLimit ? $anomaly->vehicle->min_km_per_liter : $anomaly->vehicle->max_km_per_liter;
                            @endphp
                            <li class="fleet-record">
                                <div>
                                    <a class="fleet-link" href="{{ route('fuels.edit', $anomaly->id) }}"
                                        aria-label="{{ $anomaly->vehicle?->plate }} yakıt kaydı">{{ $anomaly->vehicle?->plate }}</a>
                                    <p class="fleet-detail">{{ \Carbon\Carbon::parse($anomaly->date)->format('d.m.Y') }}</p>
                                </div>
                                <div>
                                    <b class="{{ $belowLimit ? 'fleet-overdue' : 'fleet-due' }}">{{ $anomaly->kpl }} km/L</b>
                                    <p class="fleet-detail">{{ $belowLimit ? 'Alt sınır' : 'Üst sınır' }} {{ $limit }} km/L</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <section class="fleet-panel" aria-labelledby="fleet-activity">
            <div class="fleet-panel-heading">
                <h3 id="fleet-activity">Sistem Günlüğü</h3>
                <a class="fleet-link" href="{{ route('activity-logs.index') }}">Tüm kayıtlar <span aria-hidden="true">→</span></a>
            </div>
            <ul>
                @forelse($recentActivity as $activity)
                    <li class="fleet-record">
                        <div>
                            <strong>{{ $activity->title }}</strong>
                            <p class="fleet-detail">{{ $activity->user->name ?? 'Sistem' }} · {{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </li>
                @empty
                    <li class="fleet-empty">Henüz işlem kaydı yok.</li>
                @endforelse
            </ul>
            @if($lastBackupDate)
                <p class="fleet-detail fleet-backup">Son yedek · {{ $lastBackupDate }} · {{ $lastBackupSize }}</p>
            @endif
        </section>
    </div>
</div>
@endsection
