@extends('layouts.app')

@section('title', 'Yönetim Paneli')
@section('subtitle', 'Operasyonel ve Finansal Genel Bakış')

@section('content')

@php
    $hour = now()->hour;
    $greeting = 'İyi Günler';
    if ($hour < 12) $greeting = 'Günaydın';
    elseif ($hour < 18) $greeting = 'Tünaydın';
    else $greeting = 'İyi Akşamlar';

    $chartLabels = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu'];
    $incomeSeries = [12000, 18000, 22000, 26000, 31000, 37000, 43000, (float) $monthlyIncome];
    $operationSeries = [8, 11, 13, 16, 18, 21, 24, max((int) $todayTrips, 12)];

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

<div class="space-y-8 animate-in fade-in duration-700">
    
    <!-- Üst Hoş Geldiniz ve Hızlı Aksiyonlar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}! 👋</h2>
            <div class="flex flex-wrap items-center gap-4 mt-2">
                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Sistem durumu şu an stabil. İşte bugünün özeti.</p>
                @if($lastBackupDate)
                <div class="flex items-center gap-2 px-3 py-1 bg-indigo-50 border border-indigo-100 rounded-lg shadow-sm">
                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Floppy%20Disk.png" alt="Backup" class="w-4 h-4 drop-shadow-sm" />
                    <span class="text-[10px] font-black text-indigo-700 uppercase tracking-widest">Son Yedek: {{ $lastBackupDate }} ({{ $lastBackupSize }})</span>
                </div>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('trips.create') }}" class="group relative flex items-center gap-2 overflow-hidden rounded-2xl bg-slate-900 px-6 py-3 text-sm font-black text-white shadow-2xl transition-all hover:scale-105 active:scale-95">
                <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-600 opacity-0 transition-opacity group-hover:opacity-100"></div>
                <svg class="relative w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg>
                <span class="relative">YENİ SEFER EKLE</span>
            </a>
        </div>
    </div>

    <!-- Ana İstatistik Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Kart 1: Araçlar -->
        <div class="group relative overflow-hidden rounded-[40px] bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 p-8 text-white shadow-xl shadow-blue-500/25 transition-all hover:shadow-2xl hover:-translate-y-1">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Bus.png" alt="Araçlar" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex items-center justify-between z-10">
                <div>
                    <p class="text-[11px] font-black text-blue-100/80 uppercase tracking-[0.2em] mb-2">Toplam Araç</p>
                    <h3 class="text-4xl font-black text-white leading-none">{{ $vehicleCount }}</h3>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2 relative z-10">
                <span class="flex items-center gap-1 text-[10px] font-black text-white bg-white/15 backdrop-blur px-2 py-1 rounded-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                    FİLO AKTİF
                </span>
                <span class="text-[10px] font-bold text-blue-100 uppercase tracking-widest">Sistemde Kayıtlı</span>
            </div>
        </div>

        <!-- Kart 2: Gelir -->
        <div class="group relative overflow-hidden rounded-[40px] bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 p-8 text-white shadow-xl shadow-emerald-500/25 transition-all hover:shadow-2xl hover:-translate-y-1">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Money%20Bag.png" alt="Gelir" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex items-center justify-between z-10">
                <div>
                    <p class="text-[11px] font-black text-emerald-100/80 uppercase tracking-[0.2em] mb-2">Aylık Gelir</p>
                    <h3 class="text-3xl font-black text-white leading-none">{{ number_format($monthlyIncome, 0, ',', '.') }} <span class="text-xl opacity-80">₺</span></h3>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2 relative z-10">
                <span class="flex items-center gap-1 text-[10px] font-black text-white bg-white/15 backdrop-blur px-2 py-1 rounded-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                    OPERASYONEL
                </span>
                <span class="text-[10px] font-bold text-emerald-100 uppercase tracking-widest">Bu Ayın Toplamı</span>
            </div>
        </div>

        <!-- Kart 3: Şoförler -->
        <div class="group relative overflow-hidden rounded-[40px] bg-gradient-to-br from-violet-500 via-purple-600 to-fuchsia-700 p-8 text-white shadow-xl shadow-violet-500/25 transition-all hover:shadow-2xl hover:-translate-y-1">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/People/Man%20Pilot.png" alt="Şoför" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex items-center justify-between z-10">
                <div>
                    <p class="text-[11px] font-black text-violet-100/80 uppercase tracking-[0.2em] mb-2">Toplam Şoför</p>
                    <h3 class="text-4xl font-black text-white leading-none">{{ $driverCount }}</h3>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2 relative z-10">
                <span class="flex items-center gap-1 text-[10px] font-black text-white bg-white/15 backdrop-blur px-2 py-1 rounded-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                    KADRO
                </span>
                <span class="text-[10px] font-bold text-violet-100 uppercase tracking-widest">Ekipler Hazır</span>
            </div>
        </div>

        <!-- Kart 4: Müşteriler -->
        <div class="group relative overflow-hidden rounded-[40px] bg-gradient-to-br from-amber-500 via-orange-500 to-rose-600 p-8 text-white shadow-xl shadow-amber-500/25 transition-all hover:shadow-2xl hover:-translate-y-1">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Office%20Building.png" alt="Müşteriler" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex items-center justify-between z-10">
                <div>
                    <p class="text-[11px] font-black text-amber-100/80 uppercase tracking-[0.2em] mb-2">Müşteriler</p>
                    <h3 class="text-4xl font-black text-white leading-none">{{ $customerCount }}</h3>
                </div>
            </div>
            <div class="mt-6 flex items-center gap-2 relative z-10">
                <span class="flex items-center gap-1 text-[10px] font-black text-white bg-white/15 backdrop-blur px-2 py-1 rounded-lg">
                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                    KURUMSAL
                </span>
                <span class="text-[10px] font-bold text-amber-100 uppercase tracking-widest">Partner Sayısı</span>
            </div>
        </div>
    </div>

    <!-- Grafik ve Operasyon Bölümü -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
        
        <!-- Sol Kolon: Grafik ve Önemli Tablolar -->
        <div class="xl:col-span-8 space-y-8">
            
            <!-- Araç Bakım Sağlığı KPI -->
            <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                    <div>
                        <h3 class="text-xl font-black text-slate-800">Filo Bakım Sağlığı</h3>
                        <p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-widest">Yaklaşan ve Geciken Bakımlar (Son 200 KM)</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                            <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest">Sağlıklı Araç</div>
                                <div class="text-lg font-black leading-none">{{ $healthyCount }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-4 py-2 rounded-2xl {{ $maintAlerts->count() > 0 ? 'bg-rose-50 text-rose-600 border-rose-100' : 'bg-slate-50 text-slate-400 border-slate-100' }} border">
                            <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest">Riskli Araç</div>
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
                        <h4 class="text-lg font-black text-slate-800">Tüm Filo Sağlıklı!</h4>
                        <p class="text-sm font-bold text-slate-400 mt-1">Bakımı yaklaşan veya geciken hiçbir araç bulunmuyor.</p>
                    </div>
                @endif
            </div>


            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Son Seferler -->
                <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 flex flex-col">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Son Seferler</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Filo Hareketliliği</p>
                        </div>
                        <a href="{{ route('trips.index') }}" class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-xl hover:bg-indigo-100 transition-all">TÜMÜ</a>
                    </div>

                    <div class="space-y-4 flex-1">
                        @forelse($recentTrips as $trip)
                            <div class="flex items-center gap-4 p-3 rounded-2xl border border-transparent hover:border-slate-100 hover:bg-slate-50 transition-all group">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white font-black text-[10px] shadow-lg group-hover:scale-110 transition-transform">
                                    {{ substr($trip->vehicle?->plate ?? 'TR', 0, 2) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-black text-slate-800 truncate">{{ $trip->vehicle?->plate ?? '-' }}</div>
                                    <div class="text-[11px] font-bold text-slate-400 truncate">{{ $trip->driver?->full_name ?? '-' }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[12px] font-black text-slate-900">{{ number_format($trip->trip_price ?? 0, 0, ',', '.') }} ₺</div>
                                    <div class="text-[9px] font-bold text-slate-400">{{ $trip->trip_date?->format('H:i') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm font-bold text-slate-400 text-center py-8">Kayıt yok.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Yaklaşan Belgeler -->
                <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 flex flex-col">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Kritik Belgeler</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Yasal Hatırlatmalar</p>
                        </div>
                        <a href="{{ route('documents.index') }}" class="text-[10px] font-black text-rose-600 bg-rose-50 px-3 py-1.5 rounded-xl hover:bg-rose-100 transition-all">YÖNET</a>
                    </div>

                    <div class="space-y-4 flex-1">
                        @forelse($upcomingDocuments->take(5) as $document)
                            @php
                                $daysLeft = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($document->end_date)->startOfDay(), false);
                                $color = $daysLeft <= 7 ? 'rose' : 'amber';
                            @endphp
                            <div class="flex items-center gap-4 p-3 rounded-2xl border border-transparent hover:border-slate-100 hover:bg-slate-50 transition-all">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-{{ $color }}-50 text-{{ $color }}-600 font-black text-[11px] border border-{{ $color }}-100 shadow-sm">
                                    {{ $daysLeft }}g
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-black text-slate-800 truncate">{{ $document->document_name }}</div>
                                    <div class="text-[10px] font-bold text-slate-400 truncate">{{ $document->documentable?->plate ?? $document->documentable?->full_name ?? $document->documentable?->route_name ?? 'Genel' }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] font-black text-slate-500">{{ $document->end_date?->format('d/m/Y') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm font-bold text-slate-400 text-center py-8">Kayıt yok.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Durum ve Finans -->
        <div class="xl:col-span-4 space-y-8">
            
            <!-- Operasyonel Sağlık (Mini Kartlar) -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-indigo-600 rounded-[35px] p-6 text-white shadow-xl shadow-indigo-100 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-white/10 rounded-full"></div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-indigo-200">Bugünkü Sefer</p>
                    <h4 class="text-3xl font-black mt-2">{{ $todayTrips }}</h4>
                    <p class="text-[10px] font-bold text-indigo-300 mt-2">Aktif Planlanan</p>
                </div>
                <div class="bg-slate-900 rounded-[35px] p-6 text-white shadow-xl shadow-slate-200 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-white/10 rounded-full"></div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Bakımda Olan</p>
                    <h4 class="text-3xl font-black mt-2 text-amber-400">{{ $activeMaintenances }}</h4>
                    <p class="text-[10px] font-bold text-slate-500 mt-2">Servis Bekleyen: {{ $waitingMaintenances }}</p>
                </div>
            </div>

            <!-- Finansal Özet -->
            <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-50/50 rounded-full blur-3xl"></div>
                <h3 class="text-xl font-black text-slate-800 mb-8 tracking-tight">Finansal Akış</h3>
                
                <div class="space-y-6">
                    <div class="flex items-center justify-between p-4 rounded-3xl bg-slate-50 border border-slate-100/50">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Aylık Gelir</p>
                                <p class="text-sm font-black text-slate-800">{{ number_format($monthlyIncome, 0, ',', '.') }} ₺</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-3xl bg-slate-50 border border-slate-100/50">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Yakıt Gideri</p>
                                <p class="text-sm font-black text-slate-800">{{ number_format($monthlyFuel, 0, ',', '.') }} ₺</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-3xl bg-slate-50 border border-slate-100/50">
                        <div class="flex items-center gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Trafik Cezası</p>
                                <p class="text-sm font-black text-slate-800">{{ number_format($monthlyPenalty, 0, ',', '.') }} ₺</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Net Karlılık</p>
                            <p class="text-3xl font-black mt-1 {{ $netProfit >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                {{ number_format($netProfit, 0, ',', '.') }} <span class="text-lg font-medium">₺</span>
                            </p>
                        </div>
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-2xl">
                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Money%20Bag.png" alt="Kâr" class="w-8 h-8 drop-shadow-md" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sistem Günlüğü -->
            <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 flex flex-col">
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

        </div>
    </div>

    <!-- Yakıt Tüketim Anormallikleri KPI (En Alt) -->
    @if(isset($fuelAnomalies) && $fuelAnomalies->count() > 0)
    <div class="bg-white rounded-[40px] border border-slate-100 shadow-sm p-8 mt-8">
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



@endsection
