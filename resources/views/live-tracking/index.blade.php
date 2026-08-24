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

    <!-- Açılır/Kapanır Sol Sidebar (Premium 3D) -->
    <div id="leftSidebar" class="absolute top-24 bottom-4 left-4 z-[20] w-20 hover:w-72 transition-all duration-300 ease-in-out flex flex-col pointer-events-none group">
        
        <!-- Sidebar Ana Kutu -->
        <div class="h-full w-full bg-white/80 backdrop-blur-xl rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.1),_inset_0_2px_0_rgba(255,255,255,1),_inset_0_-2px_0_rgba(0,0,0,0.05)] border border-white flex flex-col overflow-hidden pointer-events-auto transition-all duration-300">
            
            <!-- Logo / Üst Kısım -->
            <div class="h-20 flex items-center px-6 border-b border-slate-100/50">
                <div class="min-w-[32px] h-8 w-8 rounded-xl bg-indigo-600 shadow-[0_4px_15px_rgba(79,70,229,0.4),_inset_0_2px_0_rgba(255,255,255,0.2)] flex items-center justify-center text-white font-black text-sm transition-all group-hover:scale-110">
                    FM
                </div>
                <div class="ml-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
                    <h2 class="text-sm font-black text-slate-800">Filo Yönetimi</h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Araç Takip Sistemi</p>
                </div>
            </div>

            <!-- Menü Öğeleri -->
            <div class="flex-1 py-6 px-4 space-y-2 overflow-y-auto custom-scrollbar">
                
                <!-- Canlı İzleme (Aktif) -->
                <a href="#" class="flex items-center px-2 py-3 rounded-2xl bg-slate-100/50 text-indigo-600 transition-all hover:bg-white hover:shadow-md cursor-pointer group/item">
                    <div class="min-w-[40px] flex justify-center">
                        <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    </div>
                    <span class="ml-2 text-sm font-black opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Canlı Harita</span>
                </a>

                <!-- Tanımlamalar -->
                <a href="#" class="flex items-center px-2 py-3 rounded-2xl text-slate-500 transition-all hover:bg-white hover:shadow-md hover:text-slate-800 cursor-pointer group/item mt-2">
                    <div class="min-w-[40px] flex justify-center">
                        <svg class="w-6 h-6 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <span class="ml-2 text-sm font-black opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">Tanımlamalar</span>
                </a>

            </div>

            <!-- Alt Durum Bilgisi (Sadece Hover'da) -->
            <div class="p-6 border-t border-slate-100/50 bg-slate-50/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500">Sistem Durumu</span>
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 w-full"></div>
                </div>
                <p class="text-[10px] text-slate-400 font-bold mt-2 text-center uppercase tracking-wider">Senkronize</p>
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
        let isFirstLoad = true;
        const allVehicles = @json($vehicles);

        document.addEventListener("DOMContentLoaded", function() {
            initMap();
            fetchData();
            setInterval(fetchData, 3000);
        });

        function initMap() {
            // Varsayılan Merkez: Konya (Daha da yakın)
            const defaultCenter = [37.8746, 32.4932];
            map = L.map('map', { zoomControl: false }).setView(defaultCenter, 13);

            // Google Maps Beyaz (Standart) Yol Haritası
            L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
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
            let bounds = [];
            liveData.forEach(vehicle => {
                if (vehicle.Latitude && vehicle.Longitude) {
                    const lat = parseFloat(vehicle.Latitude);
                    const lng = parseFloat(vehicle.Longitude);
                    const isMoving = vehicle.Speed > 0;
                    bounds.push([lat, lng]);
                    
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

            // İlk yüklemede haritayı araçların olduğu bölgeye otomatik yakınlaştır
            if (isFirstLoad && bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
                isFirstLoad = false;
            }
        }

        function updateSidebarList(liveData) {
            // Şimdilik listeyi gizlediğimiz için bu fonksiyon boş bırakıldı.
            // Tanımlamalar sekmesinin içi daha sonra tasarlanacak.
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
