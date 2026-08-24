@extends('layouts.app')

@section('title', 'Araç Takip Sistemi')
@section('subtitle', 'Canlı İzleme ve Filo Yönetimi')

@section('content')
<div class="relative w-full h-[calc(100vh-140px)] min-h-[700px] flex gap-6">

    @if(session('success'))
        <div class="absolute top-4 left-1/2 -translate-x-1/2 z-[999] rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-700 font-bold flex items-center gap-3 shadow-xl animate-in slide-in-from-top-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Sidebar (Vehicle List) -->
    <div class="w-96 flex flex-col gap-4 relative z-10 h-full">
        <!-- Stats Card -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex-shrink-0">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-xl text-indigo-600">🛰️</div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800">Filo Durumu</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ count($vehicles) }} Araç Kayıtlı</p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="bg-emerald-50 rounded-2xl p-3 border border-emerald-100">
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Hareketli</p>
                    <p class="text-2xl font-black text-emerald-700" id="movingCount">0</p>
                </div>
                <div class="bg-rose-50 rounded-2xl p-3 border border-rose-100">
                    <p class="text-[10px] font-bold text-rose-600 uppercase tracking-wider mb-1">Duran</p>
                    <p class="text-2xl font-black text-rose-700" id="stoppedCount">0</p>
                </div>
            </div>
        </div>

        <!-- Vehicle List -->
        <div class="bg-white rounded-3xl border border-slate-200 flex-1 flex flex-col overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-slate-700 text-sm">Araç Listesi</h3>
                <span class="px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black" id="liveTag">CANLI</span>
            </div>
            
            <div id="vehiclesListContainer" class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar">
                <!-- Javascript will populate this -->
                <div class="p-8 text-center">
                    <div class="animate-spin-slow inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 text-xl mb-4">🧭</div>
                    <p class="text-sm font-bold text-slate-400">Veriler Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div class="flex-1 relative rounded-3xl overflow-hidden border border-slate-200 shadow-sm bg-white h-full z-0">
        <div id="map" class="absolute inset-0 w-full h-full"></div>
    </div>

</div>

<!-- Assign IMEI Modal -->
<div id="imeiModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closeImeiModal()"></div>
    <div class="relative w-full max-w-md rounded-[32px] bg-white shadow-2xl overflow-hidden border border-slate-100 animate-in zoom-in-95 duration-200">
        <form action="{{ route('vehicle-tracking.assign-imei') }}" method="POST">
            @csrf
            <input type="hidden" name="vehicle_id" id="modalVehicleId">
            
            <div class="p-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-lg">📱</div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Cihaz Tanımlama</h3>
                            <p class="text-xs font-bold text-slate-400" id="modalVehiclePlate">Plaka</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeImeiModal()" class="h-8 w-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 transition flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Cihaz IMEI Numarası</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                <span class="text-slate-400 font-mono text-sm">#</span>
                            </div>
                            <input type="text" name="device_imei" id="modalDeviceImei" 
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-4 py-3 text-slate-800 font-mono focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition outline-none"
                                placeholder="Örn: 353210110128749">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Cihazın arkasındaki etikette yazan 15 haneli numarayı giriniz.</p>
                    </div>
                </div>

                <div class="mt-8">
                    <button type="submit" class="w-full py-4 rounded-2xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-600/20">
                        Kaydet ve Bağla
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<style>
    .custom-vehicle-tooltip {
        background: white !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
        padding: 4px 8px !important;
        font-weight: 800 !important;
        color: #1e293b !important;
        font-size: 11px !important;
    }
    .custom-vehicle-tooltip::before { border-top-color: white !important; }
    
    .pulse-icon {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    .pulse-moving {
        background-color: #10b981;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse-green 2s infinite;
    }
    
    .pulse-stopped {
        background-color: #ef4444;
    }

    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    @keyframes spin-slow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .animate-spin-slow { animation: spin-slow 8s linear infinite; }
</style>

<script>
    let map;
    let markers = {};
    let pollingInterval;
    
    // Server'dan render edilen tüm araçları alalım (IMEI olmayanları da listede göstereceğiz)
    const allVehicles = @json($vehicles);

    document.addEventListener("DOMContentLoaded", function() {
        initMap();
        startLiveTracking();
    });

    function initMap() {
        const defaultCenter = [39.9334, 32.8597]; // Türkiye (Ankara)
        map = L.map('map', { zoomControl: false }).setView(defaultCenter, 6);

        // Aydınlık ve temiz Google Streets katmanı
        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            attribution: '&copy; Google Maps',
            maxZoom: 20
        }).addTo(map);

        // Özel zoom butonları ekle (sağ alta)
        L.control.zoom({ position: 'bottomright' }).addTo(map);
    }

    function createIcon(isMoving) {
        return L.divIcon({
            className: 'custom-vehicle-marker',
            html: `<div class="pulse-icon ${isMoving ? 'pulse-moving' : 'pulse-stopped'}"></div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });
    }

    function renderVehicles(liveData) {
        const bounds = [];
        
        liveData.forEach(vehicle => {
            if (vehicle.Latitude && vehicle.Longitude) {
                const lat = parseFloat(vehicle.Latitude);
                const lng = parseFloat(vehicle.Longitude);
                const isMoving = vehicle.Speed > 0;
                
                const popupContent = `
                    <div style="padding: 2px; font-family: sans-serif; min-width: 140px;">
                        <div style="font-weight: 900; font-size: 15px; margin-bottom: 4px; color: #1e293b;">${vehicle.LicensePlate}</div>
                        <div style="color: ${isMoving ? '#10b981' : '#ef4444'}; font-size: 13px; font-weight: 800; margin-bottom: 2px;">${vehicle.Speed} km/h</div>
                        <div style="color: #64748b; font-size: 10px; margin-bottom: 6px;">${vehicle.Datetime || '-'}</div>
                    </div>
                `;

                if (markers[vehicle.Node]) {
                    markers[vehicle.Node].setLatLng([lat, lng]);
                    markers[vehicle.Node].setIcon(createIcon(isMoving));
                    markers[vehicle.Node].setPopupContent(popupContent);
                } else {
                    const marker = L.marker([lat, lng], {
                        icon: createIcon(isMoving)
                    }).addTo(map);

                    marker.bindPopup(popupContent);
                    
                    marker.bindTooltip(vehicle.LicensePlate, {
                        permanent: true,
                        direction: 'bottom',
                        className: 'custom-vehicle-tooltip',
                        offset: [0, 8]
                    });

                    markers[vehicle.Node] = marker;
                }
                bounds.push([lat, lng]);
            }
        });
        
        // İlk yüklemede haritayı sınırla
        if (Object.keys(markers).length === liveData.length && bounds.length > 0) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
        }
    }

    function updateSidebarList(liveData) {
        const listContainer = document.getElementById('vehiclesListContainer');
        let html = '';
        
        let movingCount = 0;
        let stoppedCount = 0;

        // Tüm araçları (live data + imei olmayanlar) birleştir
        allVehicles.forEach(v => {
            // Live data içinde var mı kontrol et
            const liveInfo = liveData.find(l => l.Node === v.id);
            
            if (liveInfo) {
                // IMEI'si var ve live endpoint'ten dönmüş
                const isMoving = liveInfo.Speed > 0;
                if (isMoving) movingCount++;
                else stoppedCount++;

                html += `
                    <div class="bg-white p-3 rounded-2xl border border-slate-100 hover:border-indigo-300 hover:shadow-md transition cursor-pointer flex justify-between items-center" onclick="focusOnVehicle('${v.id}')">
                        <div>
                            <h4 class="font-black text-slate-800 text-sm">${v.license_plate}</h4>
                            <p class="text-[10px] text-slate-500 truncate w-40" title="${liveInfo.Address}">${liveInfo.Address || 'Konum bekleniyor...'}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-black text-slate-700">${liveInfo.Speed} km</span>
                            <div class="flex items-center gap-1 justify-end mt-1">
                                <div class="w-1.5 h-1.5 rounded-full ${isMoving ? 'bg-emerald-500' : 'bg-rose-500'}"></div>
                                <span class="text-[8px] font-bold ${isMoving ? 'text-emerald-600' : 'text-rose-600'}">${isMoving ? 'HAREKETLİ' : 'DURAN'}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // IMEI'si yok veya live endpoint'te gelmedi
                html += `
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex justify-between items-center opacity-70 hover:opacity-100 transition">
                        <div>
                            <h4 class="font-black text-slate-600 text-sm">${v.license_plate}</h4>
                            <p class="text-[10px] text-slate-400">Cihaz Tanımlanmamış</p>
                        </div>
                        <button onclick="openImeiModal(${v.id}, '${v.license_plate}', '${v.device_imei || ''}')" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-[10px] font-bold text-indigo-600 hover:bg-indigo-50 transition shadow-sm">
                            IMEI EKLE
                        </button>
                    </div>
                `;
            }
        });
        
        listContainer.innerHTML = html;
        document.getElementById('movingCount').innerText = movingCount;
        document.getElementById('stoppedCount').innerText = stoppedCount;
    }

    function focusOnVehicle(nodeId) {
        const marker = markers[nodeId];
        if (marker) {
            map.flyTo(marker.getLatLng(), 16, { duration: 1 });
            setTimeout(() => { marker.openPopup(); }, 1000);
        }
    }

    function startLiveTracking() {
        fetchData();
        pollingInterval = setInterval(fetchData, 3000);
    }

    function fetchData() {
        document.getElementById('liveTag').classList.add('animate-pulse');
        fetch('{{ route("vehicle-tracking.live") }}')
            .then(response => response.json())
            .then(data => {
                if (data.vehicles) {
                    renderVehicles(data.vehicles);
                    updateSidebarList(data.vehicles);
                }
                setTimeout(() => { document.getElementById('liveTag').classList.remove('animate-pulse'); }, 500);
            })
            .catch(err => console.error("Canlı takip hatası:", err));
    }

    // Modal Functions
    function openImeiModal(vehicleId, plate, currentImei) {
        document.getElementById('modalVehicleId').value = vehicleId;
        document.getElementById('modalVehiclePlate').innerText = plate;
        document.getElementById('modalDeviceImei').value = currentImei;
        document.getElementById('imeiModal').classList.remove('hidden');
    }

    function closeImeiModal() {
        document.getElementById('imeiModal').classList.add('hidden');
    }

    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeImeiModal(); });
</script>
@endsection
