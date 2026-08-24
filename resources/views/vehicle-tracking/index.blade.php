@extends('layouts.app')

@section('title', 'Araç Takip')
@section('subtitle', 'Entegrasyon Ayarları ve Canlı Takip')

@section('content')
<div class="relative w-full space-y-6">
    @if(session('success'))
        <div class="rounded-3xl bg-emerald-500/10 border border-emerald-500/20 p-5 text-emerald-400 font-bold flex items-center gap-4 animate-in fade-in slide-in-from-top-4">
            <div class="h-10 w-10 rounded-xl bg-emerald-500 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            {{ session('success') }}
        </div>
    @endif

    @if(!$setting)
        <!-- Welcome Screen -->
        <div class="relative group w-full">
            <div class="absolute -inset-1 rounded-[40px] bg-gradient-to-tr from-indigo-500/20 to-purple-600/20 blur opacity-70 group-hover:opacity-100 transition duration-500"></div>
            <div class="relative rounded-[40px] border border-white bg-white/40 shadow-xl backdrop-blur-xl p-12 text-center">
                <div class="max-w-3xl mx-auto">
                    <div class="inline-flex h-20 w-20 items-center justify-center rounded-3xl bg-indigo-500 text-white text-4xl shadow-2xl shadow-indigo-500/40 mb-8 animate-bounce">
                        🛰️
                    </div>
                    <h2 class="text-4xl font-black text-slate-900 mb-6 tracking-tight">Araç Takip Entegrasyonu</h2>
                    <p class="text-lg text-slate-500 font-medium leading-relaxed mb-12">
                        Sistemimiz Arvento, Trio Mobil ve Mobiliz servisleri ile tam entegre çalışmaktadır.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-left">
                        @foreach(['arvento' => 'A', 'trio_mobil' => 'T', 'mobiliz' => 'M'] as $provider => $initial)
                            <button type="button" onclick="selectProvider('{{ $provider }}')" class="group/card relative p-8 rounded-[35px] border-2 border-white bg-white/60 hover:bg-white hover:border-indigo-500 transition-all duration-500 hover:scale-105 shadow-sm hover:shadow-2xl">
                                <div class="h-16 w-16 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center mb-6 group-hover/card:bg-indigo-50 transition-colors text-2xl font-black text-slate-400 group-hover/card:text-indigo-500">
                                    {{ $initial }}
                                </div>
                                <h3 class="text-xl font-black text-slate-900 mb-2 capitalize">{{ str_replace('_', ' ', $provider) }}</h3>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($setting)
        <!-- Dashboard Content -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 w-full">
            <div class="bg-white/70 backdrop-blur-xl p-6 rounded-[30px] border border-white shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-100 flex items-center justify-center text-xl">🛰️</div>
                    <div class="overflow-hidden">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sistem</p>
                        <p class="text-lg font-black text-slate-900 truncate capitalize">{{ str_replace('_', ' ', $setting->provider) }}</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white/70 backdrop-blur-xl p-6 rounded-[30px] border border-white shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-xl text-emerald-600">🟢</div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Aktif Araç</p>
                        <p class="text-2xl font-black text-slate-900" id="activeVehicleCount">{{ is_array($vehicles) ? count($vehicles) : 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white/70 backdrop-blur-xl p-6 rounded-[30px] border border-white shadow-sm flex items-center justify-between gap-4">
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 flex items-center justify-center text-xl">👤</div>
                    <div class="overflow-hidden">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kullanıcı</p>
                        <p class="text-lg font-black text-slate-900 truncate">{{ $setting->username }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('vehicle-tracking.reports') }}" class="shrink-0 px-6 py-3 rounded-2xl bg-indigo-500 text-white text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition shadow-lg shadow-indigo-500/30 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Raporlar
                    </a>
                    <button type="button" onclick="selectProvider('{{ $setting->provider }}')" class="shrink-0 px-6 py-3 rounded-2xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest hover:bg-slate-800 transition shadow-lg shadow-slate-900/10">Ayarları Değiştir</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 w-full">
            <!-- List -->
            <div id="vehiclesListContainer" class="lg:col-span-4 space-y-4 max-h-[600px] overflow-y-auto pr-2 custom-scrollbar">
                @if(is_array($vehicles) && count($vehicles) > 0)
                    @foreach($vehicles as $vehicle)
                        <div class="group bg-white/70 backdrop-blur-md p-4 rounded-[24px] border border-white shadow-sm hover:shadow-xl transition-all duration-300 cursor-pointer" onclick="focusOnVehicle('{{ $vehicle['Node'] ?? '' }}')">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="h-10 w-10 shrink-0 rounded-[14px] bg-slate-100 flex items-center justify-center text-lg group-hover:bg-indigo-500 group-hover:text-white transition-colors">🚚</div>
                                    <div class="overflow-hidden">
                                        <h4 class="text-base font-black text-slate-900 truncate">{{ $vehicle['LicensePlate'] ?? 'Plakasız' }}</h4>
                                        <p class="text-[9px] font-bold text-slate-400 truncate" title="{{ $vehicle['Address'] ?? '' }}">{{ isset($vehicle['Address']) ? \Illuminate\Support\Str::limit($vehicle['Address'], 35) : 'Konum alınıyor...' }}</p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-base font-black text-indigo-600">{{ $vehicle['Speed'] ?? 0 }} <span class="text-[9px]">km/h</span></div>
                                    <div class="flex items-center gap-1 justify-end mt-1">
                                        <div class="h-1.5 w-1.5 rounded-full {{ ($vehicle['Speed'] ?? 0) > 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                                        <span class="text-[8px] font-black {{ ($vehicle['Speed'] ?? 0) > 0 ? 'text-emerald-500' : 'text-rose-500' }} uppercase tracking-wider">{{ ($vehicle['Speed'] ?? 0) > 0 ? 'HAREKETLİ' : 'DURAN' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="p-10 text-center bg-white/40 rounded-[30px] border border-white border-dashed">
                        <p class="text-slate-400 font-bold">Araç verisi bekleniyor...</p>
                    </div>
                @endif
            </div>

            <!-- Map -->
            <div class="lg:col-span-8 min-h-[600px]">
                <div class="relative h-full rounded-[40px] border border-white bg-white shadow-2xl overflow-hidden">
                    <div id="map" class="absolute inset-0 w-full h-full z-0"></div>
                    
                    @if(!(is_array($vehicles) && count($vehicles) > 0))
                        <div class="absolute inset-0 z-10 flex items-center justify-center p-8">
                            <div class="bg-white/80 backdrop-blur-md p-10 rounded-[40px] border border-white shadow-2xl max-w-sm w-full text-center">
                                <div class="animate-spin-slow inline-flex h-16 w-16 items-center justify-center rounded-full bg-indigo-500 text-white text-2xl mb-6">🧭</div>
                                <h3 class="text-xl font-black text-slate-900 mb-2">Harita Hazırlanıyor</h3>
                                <p class="text-xs text-slate-500 font-medium leading-relaxed">Konum verileri geldiğinde burada görünecek.</p>
                            </div>
                        </div>
                    @endif

                    <div class="absolute top-6 right-6 flex items-center gap-3 bg-white/90 backdrop-blur-md px-4 py-2 rounded-xl shadow-lg border border-white z-10">
                        <div class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                        </div>
                        <span class="text-[10px] font-black text-slate-900 uppercase tracking-widest">Canlı</span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Modal (Fixed at the end to prevent layout push) -->
<div id="setupForm" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md" onclick="closeSetup()"></div>
    <div class="relative w-full max-w-xl rounded-[40px] bg-white shadow-[0_35px_60px_-15px_rgba(0,0,0,0.3)] overflow-hidden border border-white animate-in zoom-in-95 duration-300">
        <form action="{{ route('vehicle-tracking.store') }}" method="POST">
            @csrf
            <input type="hidden" name="provider" id="providerInput" value="{{ $setting->provider ?? '' }}">
            
            <div class="p-10">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-indigo-500 flex items-center justify-center text-white text-xl shadow-lg">⚙️</div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900" id="selectedProviderTitle">Entegrasyon Ayarları</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Lütfen API bilgilerinizi giriniz</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeSetup()" class="h-10 w-10 rounded-xl bg-slate-50 text-slate-400 hover:bg-slate-100 transition-colors flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-4">Kullanıcı Adı</label>
                        <input type="text" name="username" value="{{ $setting->username ?? '' }}" required class="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-4">Şifre (Panel Şifresi)</label>
                        <input type="password" name="password" required class="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-4">PIN1 (App ID)</label>
                            <input type="text" name="app_id" value="{{ $setting->app_id ?? '' }}" class="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-4">PIN2 (App Key)</label>
                            <input type="text" name="app_key" value="{{ $setting->app_key ?? '' }}" class="w-full px-6 py-4 rounded-2xl bg-slate-50 border border-slate-100 text-slate-900 font-bold focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all outline-none">
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    <button type="submit" class="w-full py-5 rounded-[24px] bg-slate-900 text-white font-black text-sm hover:scale-[1.02] transition shadow-2xl">
                        Ayarları Kaydet ve Bağlan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
    .custom-vehicle-tooltip {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .custom-vehicle-tooltip::before {
        display: none !important;
    }
</style>
<script>
    let map;
    let markers = {};
    let vehiclesList = @json($vehicles);
    let pollingInterval;

    document.addEventListener("DOMContentLoaded", function() {
        initMap();
        startLiveTracking();
    });

    function initMap() {
        const defaultCenter = [39.9334, 32.8597]; // Ankara
        
        map = L.map('map').setView(defaultCenter, 6);

        // Arvento benzeri Google Maps (veya Basarsoft) görünümü için Google Streets katmanı
        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            attribution: '&copy; Google Maps',
            maxZoom: 20
        }).addTo(map);

        renderVehicles(vehiclesList, true);
    }

    function createIcon(isMoving, course) {
        if (isMoving) {
            // Arvento style moving icon (Blue arrow/circle pointing in direction)
            return L.divIcon({
                className: 'custom-vehicle-marker',
                html: `<div style="transform: rotate(${course}deg); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                          <svg width="28" height="28" viewBox="0 0 24 24" fill="#0ea5e9" stroke="white" stroke-width="2" stroke-linejoin="round">
                              <path d="M12 2L4 22l8-5 8 5z"/>
                          </svg>
                       </div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });
        } else {
            // Arvento style stopped icon (Red dot with white border)
            return L.divIcon({
                className: 'custom-vehicle-marker',
                html: `<div style="width: 16px; height: 16px; background-color: #ef4444; border: 3px solid white; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.4);"></div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
        }
    }

    function renderVehicles(vehicles, fitBounds = false) {
        const bounds = [];
        
        vehicles.forEach(vehicle => {
            if (vehicle.Latitude && vehicle.Longitude) {
                const lat = parseFloat(vehicle.Latitude);
                const lng = parseFloat(vehicle.Longitude);
                const course = parseFloat(vehicle.Course || 0);
                const isMoving = vehicle.Speed > 0;
                const color = isMoving ? '#10b981' : '#ef4444'; 
                
                const popupContent = `
                    <div style="padding: 5px; font-family: sans-serif; min-width: 150px;">
                        <div style="font-weight: 900; font-size: 16px; margin-bottom: 4px; color: #0f172a;">${vehicle.LicensePlate}</div>
                        <div style="color: ${color}; font-size: 14px; font-weight: 800; margin-bottom: 2px;">${vehicle.Speed} km/h <span style="font-size: 10px; color: #64748b;">${isMoving ? '(Hareketli)' : '(Duran)'}</span></div>
                        <div style="color: #64748b; font-size: 11px; margin-bottom: 6px;">Tarih: ${vehicle.Datetime || '-'}</div>
                        <div style="color: #475569; font-size: 11px; margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 6px; line-height: 1.4;">${vehicle.Address || 'Konum alınamıyor'}</div>
                    </div>
                `;

                if (markers[vehicle.Node]) {
                    // Update existing marker
                    markers[vehicle.Node].setLatLng([lat, lng]);
                    markers[vehicle.Node].setIcon(createIcon(isMoving, course));
                    markers[vehicle.Node].setPopupContent(popupContent);
                    // Tooltip text doesn't change, but we ensure it stays
                } else {
                    // Create new marker
                    const marker = L.marker([lat, lng], {
                        icon: createIcon(isMoving, course),
                        title: vehicle.LicensePlate
                    }).addTo(map);

                    marker.bindPopup(popupContent);
                    
                    // Add permanent label under the marker
                    marker.bindTooltip(`
                        <div style="font-weight: 800; color: #0f172a; text-shadow: 1px 1px 0 #fff, -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff;">
                            ${vehicle.LicensePlate}
                        </div>
                    `, {
                        permanent: true,
                        direction: 'bottom',
                        className: 'custom-vehicle-tooltip',
                        offset: [0, isMoving ? 10 : 5]
                    });

                    markers[vehicle.Node] = marker;
                }
                
                bounds.push([lat, lng]);
            }
        });
        
        if (fitBounds && bounds.length > 0) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    function updateSidebarList(vehicles) {
        const listContainer = document.getElementById('vehiclesListContainer');
        if (!listContainer) return;

        if (vehicles.length === 0) {
            listContainer.innerHTML = `
                <div class="p-10 text-center bg-white/40 rounded-[30px] border border-white border-dashed">
                    <p class="text-slate-400 font-bold">Araç verisi bulunamadı.</p>
                </div>`;
            return;
        }

        let html = '';
        vehicles.forEach(vehicle => {
            const isMoving = vehicle.Speed > 0;
            const statusColor = isMoving ? 'text-emerald-500' : 'text-rose-500';
            const dotColor = isMoving ? 'bg-emerald-500' : 'bg-rose-500';
            const statusText = isMoving ? 'HAREKETLİ' : 'DURAN';

            html += `
                <div class="group bg-white/70 backdrop-blur-md p-4 rounded-[24px] border border-white shadow-sm hover:shadow-xl transition-all duration-300 cursor-pointer" onclick="focusOnVehicle('${vehicle.Node}')">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="h-10 w-10 shrink-0 rounded-[14px] bg-slate-100 flex items-center justify-center text-lg group-hover:bg-indigo-500 group-hover:text-white transition-colors">🚚</div>
                            <div class="overflow-hidden">
                                <h4 class="text-base font-black text-slate-900 truncate">${vehicle.LicensePlate}</h4>
                                <p class="text-[9px] font-bold text-slate-400 truncate" title="${vehicle.Address || ''}">${vehicle.Address ? vehicle.Address.substring(0, 35) + '...' : 'Konum alınıyor...'}</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-base font-black text-indigo-600">${vehicle.Speed || 0} <span class="text-[9px]">km/h</span></div>
                            <div class="flex items-center gap-1 justify-end mt-1">
                                <div class="h-1.5 w-1.5 rounded-full ${dotColor}"></div>
                                <span class="text-[8px] font-black ${statusColor} uppercase tracking-wider">${statusText}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
        document.getElementById('activeVehicleCount').innerText = vehicles.length;
    }

    function focusOnVehicle(nodeId) {
        const marker = markers[nodeId];
        if (marker) {
            map.flyTo(marker.getLatLng(), 15, { duration: 1 });
            setTimeout(() => { marker.openPopup(); }, 1000);
        }
    }

    function startLiveTracking() {
        // Update list on initial load
        updateSidebarList(vehiclesList);

        // Fetch new data every 2 seconds
        pollingInterval = setInterval(() => {
            fetch('{{ route("vehicle-tracking.live") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.vehicles && Array.isArray(data.vehicles)) {
                        renderVehicles(data.vehicles, false); // false = dont zoom out every 2s
                        updateSidebarList(data.vehicles);
                    }
                })
                .catch(err => console.error("Canlı takip hatası:", err));
        }, 2000);
    }

    function selectProvider(provider) {
        document.getElementById('providerInput').value = provider;
        document.getElementById('setupForm').classList.remove('hidden');
    }

    function closeSetup() {
        document.getElementById('setupForm').classList.add('hidden');
    }

    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSetup(); });
</script>

<style>
    @keyframes spin-slow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .animate-spin-slow { animation: spin-slow 8s linear infinite; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
@endsection
