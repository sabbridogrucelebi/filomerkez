<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filomerkez - Canlı Araç Takip Paneli</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .pulse-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .pulse-moving {
            background-color: #10b981;
            animation: pulse-green 2s infinite;
        }
        .pulse-stopped {
            background-color: #ef4444;
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .custom-vehicle-tooltip {
            background: white !important;
            border: none !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
            padding: 6px 12px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            font-size: 12px !important;
        }
        .custom-vehicle-tooltip::before { display: none !important; }
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-100 font-sans antialiased text-slate-800">

    <!-- Harita Katmanı (Arka Plan) -->
    <div id="map" class="absolute inset-0 w-full h-full z-0"></div>

    <!-- Üst Menü / Navbar -->
    <div class="absolute top-4 left-4 right-4 z-10 flex justify-between items-center pointer-events-none">
        <div class="glass-panel px-6 py-3 rounded-2xl flex items-center gap-4 shadow-xl pointer-events-auto">
            <div class="h-10 w-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-black text-xl shadow-lg shadow-indigo-600/30">FM</div>
            <div>
                <h1 class="text-lg font-black tracking-tight text-slate-900 leading-none">Canlı Takip</h1>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">Filomerkez Premium</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3 pointer-events-auto">
            @if(session('success'))
                <div class="glass-panel px-4 py-2 rounded-xl text-emerald-600 font-bold text-xs flex items-center gap-2 border-emerald-200 shadow-lg animate-bounce">
                    ✅ {{ session('success') }}
                </div>
            @endif
            <a href="{{ route('dashboard') }}" class="glass-panel h-12 px-6 rounded-2xl flex items-center gap-2 text-sm font-bold text-slate-700 hover:text-indigo-600 hover:bg-white transition-all shadow-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Panele Dön
            </a>
        </div>
    </div>

    <!-- Sol Panel (Araç Listesi) -->
    <div class="absolute top-24 bottom-4 left-4 w-96 z-10 flex flex-col gap-4 pointer-events-none">
        
        <!-- İstatistikler -->
        <div class="glass-panel p-5 rounded-3xl shadow-2xl pointer-events-auto">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest">Filo Özeti</h2>
                <div class="flex items-center gap-2 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                    <span class="text-[10px] font-black">CANLI</span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white/60 rounded-2xl p-4 border border-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Hareketli</p>
                    <p class="text-3xl font-black text-emerald-600" id="movingCount">0</p>
                </div>
                <div class="bg-white/60 rounded-2xl p-4 border border-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Duran</p>
                    <p class="text-3xl font-black text-rose-600" id="stoppedCount">0</p>
                </div>
            </div>
        </div>

        <!-- Araç Listesi -->
        <div class="glass-panel rounded-3xl shadow-2xl flex-1 flex flex-col overflow-hidden pointer-events-auto">
            <div class="p-5 border-b border-white/50 flex justify-between items-center bg-white/40">
                <h3 class="font-black text-slate-800 text-sm">Tüm Araçlar</h3>
                <span class="text-xs font-bold text-slate-500 bg-white px-2 py-1 rounded-lg shadow-sm border border-slate-100">{{ count($vehicles) }}</span>
            </div>
            
            <div id="vehiclesListContainer" class="flex-1 overflow-y-auto p-3 space-y-2 custom-scrollbar">
                <div class="p-8 text-center text-slate-500 font-bold text-sm">Yükleniyor...</div>
            </div>
        </div>
    </div>

    <!-- Cihaz Tanımlama Modalı -->
    <div id="imeiModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm transition-all duration-300">
        <div class="relative w-full max-w-md rounded-[32px] bg-white shadow-2xl overflow-hidden border border-slate-100 scale-95 opacity-0 transition-all duration-300" id="imeiModalContent">
            <form action="{{ route('vehicle-tracking.assign-imei') }}" method="POST">
                @csrf
                <input type="hidden" name="vehicle_id" id="modalVehicleId">
                
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 text-xl">📱</div>
                            <div>
                                <h3 class="text-lg font-black text-slate-800">Cihaz Ekle</h3>
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1" id="modalVehiclePlate">Plaka</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeImeiModal()" class="h-8 w-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-rose-100 hover:text-rose-600 transition flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-2">Cihaz IMEI Numarası</label>
                            <input type="text" name="device_imei" id="modalDeviceImei" 
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-mono text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition outline-none shadow-inner"
                                placeholder="Örn: 353210110128749">
                            <p class="text-[10px] text-slate-400 mt-3 font-medium">GPS cihazının arkasındaki 15 haneli numarayı giriniz.</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full py-4 rounded-2xl bg-indigo-600 text-white font-black text-sm hover:bg-indigo-700 hover:-translate-y-0.5 transition-all shadow-xl shadow-indigo-600/30">
                            Kaydet ve Bağla
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let map;
        let markers = {};
        const allVehicles = @json($vehicles);

        document.addEventListener("DOMContentLoaded", function() {
            initMap();
            fetchData();
            setInterval(fetchData, 3000);
        });

        function initMap() {
            const defaultCenter = [39.9334, 32.8597];
            map = L.map('map', { zoomControl: false }).setView(defaultCenter, 6);

            // Google Maps Hybrid Katmanı (Premium Görünüm)
            L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                attribution: '© Google Maps'
            }).addTo(map);

            L.control.zoom({ position: 'bottomright' }).addTo(map);
        }

        function createIcon(isMoving) {
            return L.divIcon({
                className: 'custom-vehicle-marker',
                html: `<div class="pulse-icon ${isMoving ? 'pulse-moving' : 'pulse-stopped'}"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });
        }

        function renderVehicles(liveData) {
            liveData.forEach(vehicle => {
                if (vehicle.Latitude && vehicle.Longitude) {
                    const lat = parseFloat(vehicle.Latitude);
                    const lng = parseFloat(vehicle.Longitude);
                    const isMoving = vehicle.Speed > 0;
                    
                    const popupContent = `
                        <div style="font-family: sans-serif; min-width: 160px; padding: 2px;">
                            <div style="font-weight: 900; font-size: 16px; margin-bottom: 4px; color: #0f172a;">${vehicle.LicensePlate}</div>
                            <div style="color: ${isMoving ? '#10b981' : '#ef4444'}; font-size: 14px; font-weight: 900; margin-bottom: 4px;">${vehicle.Speed} km/h</div>
                            <div style="color: #64748b; font-size: 10px;">${vehicle.Datetime || '-'}</div>
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
                            permanent: true, direction: 'bottom', className: 'custom-vehicle-tooltip', offset: [0, 10]
                        });
                        markers[vehicle.Node] = marker;
                    }
                }
            });
        }

        function updateSidebarList(liveData) {
            const listContainer = document.getElementById('vehiclesListContainer');
            let html = '';
            let movingCount = 0; let stoppedCount = 0;

            allVehicles.forEach(v => {
                const liveInfo = liveData.find(l => l.Node === v.id);
                
                if (liveInfo) {
                    const isMoving = liveInfo.Speed > 0;
                    if (isMoving) movingCount++; else stoppedCount++;

                    html += `
                        <div class="bg-white/80 p-3 rounded-2xl border border-white hover:border-indigo-300 hover:shadow-lg transition-all cursor-pointer flex justify-between items-center group" onclick="focusOnVehicle('${v.id}')">
                            <div class="flex items-center gap-3">
                                <div class="w-2 h-10 rounded-full ${isMoving ? 'bg-emerald-500' : 'bg-rose-500'} shadow-sm"></div>
                                <div>
                                    <h4 class="font-black text-slate-800 text-sm group-hover:text-indigo-600 transition-colors">${v.license_plate}</h4>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">${isMoving ? 'HAREKETLİ' : 'DURAN'}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-base font-black ${isMoving ? 'text-emerald-600' : 'text-slate-600'}">${liveInfo.Speed}</span>
                                <span class="text-[9px] font-bold text-slate-400">km/h</span>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-100 flex justify-between items-center opacity-80 hover:opacity-100 transition-all">
                            <div>
                                <h4 class="font-black text-slate-600 text-sm">${v.license_plate}</h4>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">CİHAZ YOK</p>
                            </div>
                            <button onclick="openImeiModal(${v.id}, '${v.license_plate}', '${v.device_imei || ''}')" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-[10px] font-black text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all shadow-sm">
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
                map.flyTo(marker.getLatLng(), 17, { duration: 1.5 });
                setTimeout(() => { marker.openPopup(); }, 1500);
            }
        }

        function fetchData() {
            fetch('{{ route("vehicle-tracking.live") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.vehicles) {
                        renderVehicles(data.vehicles);
                        updateSidebarList(data.vehicles);
                    }
                }).catch(err => console.error("Takip hatası:", err));
        }

        function openImeiModal(vehicleId, plate, currentImei) {
            document.getElementById('modalVehicleId').value = vehicleId;
            document.getElementById('modalVehiclePlate').innerText = plate;
            document.getElementById('modalDeviceImei').value = currentImei;
            document.getElementById('imeiModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('imeiModalContent').classList.remove('scale-95', 'opacity-0');
                document.getElementById('imeiModalContent').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeImeiModal() {
            document.getElementById('imeiModalContent').classList.remove('scale-100', 'opacity-100');
            document.getElementById('imeiModalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => { document.getElementById('imeiModal').classList.add('hidden'); }, 300);
        }

        window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeImeiModal(); });
    </script>
</body>
</html>
