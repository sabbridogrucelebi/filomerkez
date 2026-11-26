@extends('layouts.app')

@section('title', 'Yönetim Paneli')
@section('subtitle', 'Filo ve operasyon özeti')

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

<style>
    .fleet-summary { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 12px; }
    .fleet-summary div { padding: 18px 20px; border: 1px solid #dce4ee; border-radius: 16px; background: white; }
    .fleet-summary dt { color: #53657a; font-size: 13px; font-weight: 600; }
    .fleet-summary dd { margin-top: 5px; font-size: clamp(20px,2vw,30px); font-weight: 800; color: #172643; overflow-wrap: anywhere; }
    .fleet-hero { padding: clamp(20px,3vw,36px); border-radius: 22px; background: linear-gradient(110deg,#142440,#244671); color: white; }
    .fleet-hero header, .fleet-today { display: flex; align-items: center; flex-wrap: wrap; gap: 14px 28px; }
    .fleet-hero header { justify-content: space-between; }
    .fleet-hero h2 { font-size: clamp(23px,3vw,34px); font-weight: 800; }
    .fleet-hero p { color: #dbe8f9; font-size: 13px; }
    .fleet-hero a { color: #172643; background: white; border-radius: 10px; padding: 10px 16px; font-weight: 700; }
    .fleet-today { border-top: 1px solid #ffffff38; margin-top: 18px; padding-top: 15px; font-size: 14px; }
    .fleet-today strong { font-size: 20px; margin-right: 4px; }
    .fleet-columns { display: grid; grid-template-columns: minmax(0,1.6fr) minmax(0,1fr); gap: 20px; align-items: start; }
    .fleet-stack { display: grid; gap: 20px; min-width: 0; }
    @media (max-width: 1200px) { .fleet-columns { grid-template-columns: minmax(0,1fr); } }
    @media (max-width: 720px) { .fleet-summary { grid-template-columns: repeat(2,minmax(0,1fr)); } .fleet-summary div { padding: 14px; } }
</style>

<div class="space-y-6">
    <section class="fleet-hero">
        <header>
            <div>
                <p>{{ now()->locale('tr')->translatedFormat('d F Y, l') }}</p>
                <h2>{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}</h2>
            </div>
            @if(auth()->user()->hasPermission('trips.view'))
                <a href="{{ route('trips.create') }}">+ Yeni sefer ekle</a>
            @endif
        </header>
        <div class="fleet-today">
            <span><strong>{{ $todayTrips }}</strong> Bugünkü sefer</span>
            <span><strong>{{ $activeMaintenances }}</strong> Devam eden bakım</span>
            <span><strong>{{ $waitingMaintenances }}</strong> Bekleyen bakım</span>
        </div>
    </section>

    <dl class="fleet-summary" aria-label="Filo özeti">
        <div><dt>Toplam araç</dt><dd>{{ $vehicleCount }}</dd></div>
        <div><dt>Şoför</dt><dd>{{ $driverCount }}</dd></div>
        <div><dt>Müşteri</dt><dd>{{ $customerCount }}</dd></div>
        <div><dt>Aylık gelir</dt><dd>{{ number_format($monthlyIncome, 0, ',', '.') }} ₺</dd></div>
    </dl>

    <div class="fleet-columns">
        <div class="fleet-stack">
            <!-- Araç Bakım Sağlığı KPI -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">Filo Bakım Sağlığı</h3>
                        <p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-widest">Yaklaşan ve Geciken Bakımlar (Son 200 KM)</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest">Uyarısız araç</div>
                                <div class="text-lg font-black leading-none">{{ $healthyCount }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2 rounded-2xl {{ $maintAlerts->count() > 0 ? 'bg-rose-50 text-rose-600 border-rose-100' : 'bg-slate-50 text-slate-400 border-slate-100' }} border">
                            <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest">Bakım uyarısı</div>
                                <div class="text-lg font-black leading-none">{{ $maintAlerts->count() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($maintAlerts->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($maintAlerts as $alert)
                            <div class="group relative overflow-hidden rounded-[24px] border {{ $alert['critical'] ? 'border-rose-200 bg-rose-50/50' : 'border-amber-200 bg-amber-50/50' }} p-5 transition-all hover:shadow-lg">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $alert['critical'] ? 'bg-rose-200 text-rose-700' : 'bg-amber-200 text-amber-700' }} font-black shadow-sm">
                                            @if($alert['critical'])
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                            @else
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26" /></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-sm font-black text-slate-800">{{ $alert['vehicle']->plate }}</div>
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ $alert['type'] }}</div>
                                        </div>
                                    </div>
                                    <a href="{{ route('vehicles.show', ['vehicle' => $alert['vehicle']->id, 'tab' => 'maintenances']) }}" class="flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm hover:scale-110 transition-transform text-slate-400 hover:text-indigo-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </a>
                                </div>
                                
                                <div>
                                    @if($alert['critical'])
                                        <div class="text-rose-600 text-[10px] font-bold uppercase tracking-widest">Gecikme Miktarı</div>
                                        <div class="text-2xl font-black text-rose-700 mt-1">{{ number_format(abs($alert['remaining']), 0, ',', '.') }} KM</div>
                                    @else
                                        <div class="text-amber-600 text-[10px] font-bold uppercase tracking-widest">Kalan Mesafe</div>
                                        <div class="text-2xl font-black text-amber-700 mt-1">{{ number_format($alert['remaining'], 0, ',', '.') }} KM</div>
                                    @endif
                                </div>
                                
                                <!-- Progress Bar effect -->
                                <div class="absolute bottom-0 left-0 h-1 w-full {{ $alert['critical'] ? 'bg-rose-200' : 'bg-amber-200' }}">
                                    @php
                                        $percent = $alert['critical'] ? 100 : (1 - ($alert['remaining'] / 200)) * 100;
                                    @endphp
                                    <div class="h-full {{ $alert['critical'] ? 'bg-rose-500' : 'bg-amber-500' }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 rounded-[24px] bg-slate-50 border border-slate-100 text-emerald-400">
                        <svg class="w-16 h-16 drop-shadow-sm mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                        <h4 class="text-lg font-black text-slate-800">Bakım uyarısı yok</h4>
                        <p class="text-sm font-bold text-slate-400 mt-1">Bakımı yaklaşan veya geciken hiçbir araç bulunmuyor.</p>
                    </div>
                @endif
            </div>


    @if(isset($fuelAnomalies) && $fuelAnomalies->count() > 0)
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-xl font-black text-slate-800">Yakıt Tüketim Uyarıları</h3>
                <p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-widest">Belirlenen Limitleri Aşan Kayıtlar</p>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-rose-50 text-rose-600 border-rose-100 border">
                <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <div>
                    <div class="text-[10px] font-black uppercase tracking-widest">Riskli Kayıt</div>
                    <div class="text-lg font-black leading-none">{{ $fuelAnomalies->count() }}</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($fuelAnomalies as $anomaly)
                <div class="group relative overflow-hidden rounded-[24px] border {{ $anomaly->anomaly_type == 'high' ? 'border-amber-200 bg-amber-50/50' : 'border-rose-200 bg-rose-50/50' }} p-5 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $anomaly->anomaly_type == 'high' ? 'bg-amber-200 text-amber-700' : 'bg-rose-200 text-rose-700' }} font-black shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div>
                                <div class="text-sm font-black text-slate-800">{{ $anomaly->vehicle?->plate }}</div>
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ \Carbon\Carbon::parse($anomaly->date)->format('d.m.Y') }}</div>
                            </div>
                        </div>
                        <a href="{{ route('fuels.edit', $anomaly->id) }}" class="flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm hover:scale-110 transition-transform text-slate-400 hover:text-indigo-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </a>
                    </div>
                    
                    <div>
                        @if($anomaly->anomaly_type == 'low')
                            <div class="text-rose-600 text-[10px] font-bold uppercase tracking-widest">Limit Altı (Min: {{ $anomaly->vehicle->min_km_per_liter }})</div>
                            <div class="text-2xl font-black text-rose-700 mt-1">{{ $anomaly->kpl }} KM / L</div>
                        @else
                            <div class="text-amber-600 text-[10px] font-bold uppercase tracking-widest">Aşırı Tüketim (Maks: {{ $anomaly->vehicle->max_km_per_liter }})</div>
                            <div class="text-2xl font-black text-amber-700 mt-1">{{ $anomaly->kpl }} KM / L</div>
                        @endif
                    </div>
                    
                    <div class="absolute bottom-0 left-0 h-1 w-full {{ $anomaly->anomaly_type == 'high' ? 'bg-amber-500' : 'bg-rose-500' }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
        </div>
        <div class="fleet-stack">
            <!-- Sistem Günlüğü -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-lg font-black text-slate-800">Sistem Günlüğü</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Son Aktiviteler</p>
                    </div>
                </div>

                <div class="space-y-6 flex-1">
                    @forelse($recentActivity as $activity)
                        <div class="flex gap-4 group">
                            <div class="relative">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-50 text-slate-500 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-all border border-slate-100">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                @if(!$loop->last)
                                    <div class="absolute top-10 left-1/2 -translate-x-1/2 w-px h-6 bg-slate-100"></div>
                                @endif
                            </div>
                            <div>
                                <div class="text-[13px] font-black text-slate-800 leading-tight">{{ $activity->title }}</div>
                                <div class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-tight">{{ $activity->user->name ?? 'Sistem' }} • {{ $activity->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs font-bold text-slate-400 text-center py-4 uppercase">Kayıt yok.</p>
                    @endforelse
                </div>
                <a href="{{ route('activity-logs.index') }}" class="block w-full text-center mt-8 py-3 rounded-2xl bg-slate-50 text-[10px] font-black text-slate-500 hover:bg-slate-900 hover:text-white transition-all uppercase tracking-widest">TÜM KAYITLAR</a>
            </div>

            @if($lastBackupDate)
                <p class="text-sm text-slate-600">Son yedek · {{ $lastBackupDate }} · {{ $lastBackupSize }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
