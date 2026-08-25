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
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
        
        .pulse-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .pulse-moving {
            background-color: #06b6d4;
            animation: pulse-cyan 2s infinite;
        }
        .pulse-idle {
            background-color: #a855f7;
            animation: pulse-purple 2s infinite;
        }
        .pulse-stopped {
            background-color: #ef4444;
        }
        @keyframes pulse-cyan {
            0% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.7); }
            70% { box-shadow: 0 0 0 12px rgba(6, 182, 212, 0); }
            100% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0); }
        }
        @keyframes pulse-purple {
            0% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0.7); }
            70% { box-shadow: 0 0 0 12px rgba(168, 85, 247, 0); }
            100% { box-shadow: 0 0 0 0 rgba(168, 85, 247, 0); }
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

        /* Premium 3D Glassmorphism - Sidebar Intro Animation */
        #blueSidebar {
            width: 280px;
            background: linear-gradient(145deg, rgba(30, 58, 138, 0.8), rgba(15, 23, 42, 0.9));
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.05), 10px 0 30px rgba(0, 0, 0, 0.4);
            transition: transform 0.8s cubic-bezier(0.2,0.8,0.2,1);
        }
        #blueSidebar.sidebar-hidden {
            transform: translateX(-100%);
        }
        #blueSidebar.sidebar-visible {
            transform: translateX(0);
        }

        /* Premium 3D Glassmorphism - Navbar */
        #topNavbar {
            background: linear-gradient(145deg, rgba(30, 58, 138, 0.8), rgba(15, 23, 42, 0.9));
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.05), 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            transition: transform 0.6s cubic-bezier(0.2,0.8,0.2,1), opacity 0.6s ease;
        }
        #topNavbar.navbar-hidden {
            transform: translateY(-100%);
            opacity: 0;
        }
        #topNavbar.navbar-visible {
            transform: translateY(0);
            opacity: 1;
        }

        /* Premium 3D Button */
        .btn-3d {
            position: relative;
            background: linear-gradient(145deg, rgba(99, 102, 241, 0.9), rgba(67, 56, 202, 0.9));
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 14px;
            color: white;
            font-weight: 900;
            font-size: 13px;
            padding: 10px 18px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.3), 0 6px 0 #1e1b4b, 0 12px 20px rgba(0, 0, 0, 0.4);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .btn-3d:hover {
            transform: translateY(-2px);
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.4), 0 8px 0 #1e1b4b, 0 16px 25px rgba(0, 0, 0, 0.5);
            background: linear-gradient(145deg, rgba(129, 140, 248, 0.95), rgba(79, 70, 229, 0.95));
        }
        .btn-3d:active {
            transform: translateY(4px);
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.1), 0 2px 0 #1e1b4b, 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        /* 3D Sidebar Menu Item */


        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            border-radius: 16px;
            color: rgba(199,210,254,0.9);
            font-weight: 800;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1px solid transparent;
            background: transparent;
            width: 100%;
            text-align: left;
        }
        .sidebar-item:hover {
            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
            transform: translateX(4px);
        }
        .sidebar-item.active {
            background: linear-gradient(145deg, rgba(99, 102, 241, 0.2), rgba(67, 56, 202, 0.1));
            color: white;
            border: 1px solid rgba(99, 102, 241, 0.4);
            box-shadow: inset 0 2px 4px rgba(255,255,255,0.1), 0 8px 20px rgba(0,0,0,0.3);
            transform: scale(1.02);
        }
        .sidebar-item.active::before {
            content: '';
            position: absolute;
            left: -4px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 32px;
            background: linear-gradient(180deg, #818cf8, #4338ca);
            border-radius: 0 8px 8px 0;
            box-shadow: 0 0 15px rgba(129, 140, 248, 0.8);
        }
        .sidebar-item svg {
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.4));
            transition: all 0.3s ease;
        }
        .sidebar-item:hover svg, .sidebar-item.active svg {
            color: #818cf8;
            transform: scale(1.1) rotate(-5deg);
        }
        .sidebar-item .item-text {
            display: flex;
            flex-direction: column;
        }
        .sidebar-item .item-text span:last-child {
            font-size: 10px;
            font-weight: 700;
            color: rgba(165,180,252,0.7);
            margin-top: 2px;
        }

        /* Araç Marker Konteyneri ve Süzülme Animasyonu */
        .custom-vehicle-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 1.5s linear; /* Yumuşak kayma animasyonu */
        }
        
        /* Harita katman seçicinin (topright) üst menünün altında kalmaması için */
        .leaflet-top.leaflet-right {
            margin-top: 80px;
            margin-right: 20px;
        }
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-100 font-sans antialiased text-slate-800">

    <!-- Harita Katmanı (Arka Plan) -->
    <div id="map" class="absolute inset-0 w-full h-full z-0"></div>

    <!-- Üst Navbar (Mavi Gradient) -->
    <div id="topNavbar" class="navbar-hidden absolute top-0 left-[280px] right-0 z-[900] h-16 flex justify-between items-center px-6">
        <!-- Sol: Arama -->
        <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:12px; display:flex; align-items:center; padding:0 16px; height:40px; width:360px;">
            <svg style="width:18px;height:18px;color:rgba(199,210,254,0.7);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" placeholder="Plaka veya lokasyon ara..." style="background:transparent; border:none; outline:none; color:white; font-weight:700; font-size:13px; margin-left:10px; width:100%;" class="placeholder-indigo-300/60">
        </div>

        <!-- Sağ: Butonlar -->
        <div style="display:flex; align-items:center; gap:12px;">
            @if(session('success'))
                <div style="background:#10b981; color:white; padding:8px 16px; border-radius:12px; font-weight:800; font-size:12px; display:flex; align-items:center; gap:6px; box-shadow:0 4px 15px rgba(16,185,129,0.4);">
                    ✅ {{ session('success') }}
                </div>
            @endif
            <!-- Panele Dön 3D Buton -->
            <a href="{{ route('dashboard') }}" class="btn-3d">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
                Panele Dön
            </a>
        </div>
    </div>

    <!-- Mavi Premium Full-Height Sidebar (Her Zaman Açık, Yazılar Okunur) -->
    <div id="blueSidebar" class="sidebar-hidden absolute top-0 bottom-0 left-0 z-[1000] flex flex-col overflow-hidden">
        
        <!-- Üst Kısım: Logo -->
        <div style="height:80px; display:flex; align-items:center; padding:0 24px; border-bottom:1px solid rgba(99,102,241,0.2); flex-shrink:0;">
            <div style="position:relative; width:42px; height:42px; border-radius:14px; background:white; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(0,0,0,0.2); flex-shrink:0;">
                <span style="color:#4338ca; font-weight:900; font-size:15px; letter-spacing:-1px;">FM</span>
                <div style="position:absolute; right:-4px; top:-4px; width:12px; height:12px; background:#34d399; border-radius:50%; border:2px solid #4338ca;"></div>
            </div>
            <div style="margin-left:16px;">
                <h1 style="font-size:20px; font-weight:900; color:white; letter-spacing:-0.5px; line-height:1; margin:0 0 4px 0;">FiloMerkez</h1>
                <p style="font-size:10px; font-weight:800; color:rgba(165,180,252,0.8); text-transform:uppercase; letter-spacing:3px; margin:0;">Premium Takip</p>
            </div>
        </div>

        <!-- Ana Menü -->
        <div style="flex:1; padding:28px 16px; display:flex; flex-direction:column; gap:8px; overflow-y:auto;" class="custom-scrollbar">
            
            <!-- Canlı Harita (Aktif) -->
            <button class="sidebar-item active" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <div class="item-text">
                    <span>Canlı Harita</span>
                    <span>Tüm filoyu anlık izle</span>
                </div>
            </button>

            <!-- Tanımlamalar -->
            <a href="{{ route('vehicle-tracking.definitions') }}" class="sidebar-item" style="position:relative; text-decoration:none;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <div class="item-text">
                    <span>Cihaz Tanımlamaları</span>
                    <span>IMEI ve Araç eşleştirme</span>
                </div>
            </a>

        </div>

        <!-- Alt Durum Kutusu -->
        <div style="padding:16px 20px 28px 20px; flex-shrink:0;">
            <div style="padding:16px; border-radius:16px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); backdrop-filter:blur(10px);">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <span style="font-size:12px; font-weight:700; color:white;">Sunucu Bağlantısı</span>
                    <span style="position:relative; display:flex; width:10px; height:10px;">
                        <span class="animate-ping" style="position:absolute;width:100%;height:100%;border-radius:50%;background:#34d399;opacity:0.75;"></span>
                        <span style="position:relative;display:inline-flex;border-radius:50%;width:10px;height:10px;background:#34d399;"></span>
                    </span>
                </div>
                <div style="height:6px; width:100%; background:rgba(255,255,255,0.1); border-radius:999px; overflow:hidden; margin-bottom:8px;">
                    <div class="animate-pulse" style="height:100%; background:linear-gradient(90deg,#34d399,#6ee7b7); width:100%;"></div>
                </div>
                <p style="font-size:10px; color:#6ee7b7; font-weight:900; letter-spacing:2px; text-transform:uppercase;">TCP Canlı Akış Aktif</p>
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

            // Başlangıçta sidebar ve navbar gelsin
            setTimeout(function() {
                document.getElementById('blueSidebar').classList.remove('sidebar-hidden');
                document.getElementById('blueSidebar').classList.add('sidebar-visible');
            }, 500);
            setTimeout(function() {
                document.getElementById('topNavbar').classList.remove('navbar-hidden');
                document.getElementById('topNavbar').classList.add('navbar-visible');
            }, 800);

            // 5 saniye sonra gizle (Eğer üzerine gelinmezse)
            let hideTimeout;
            
            function resetHideTimer() {
                clearTimeout(hideTimeout);
                document.getElementById('blueSidebar').classList.remove('sidebar-hidden');
                document.getElementById('blueSidebar').classList.add('sidebar-visible');
                
                hideTimeout = setTimeout(() => {
                    document.getElementById('blueSidebar').classList.remove('sidebar-visible');
                    document.getElementById('blueSidebar').classList.add('sidebar-hidden');
                }, 5000);
            }

            // Mouse hareketinde sayacı sıfırla (sadece sidebar alanı veya harita geneli)
            document.addEventListener('mousemove', resetHideTimer);
            document.addEventListener('click', resetHideTimer);
            
            // İlk sayacı başlat
            resetHideTimer();
        });

        function initMap() {
            // Varsayılan Merkez: Konya
            const defaultCenter = [37.8746, 32.4932];
            map = L.map('map', { zoomControl: false }).setView(defaultCenter, 13);

            // Google Maps Beyaz (Standart) Yol Haritası
            const googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                attribution: '© Google Maps'
            });

            // Google Hybrid (Uydu)
            const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                attribution: '© Google Maps'
            });

            // Google Traffic (Trafik)
            const googleTraffic = L.tileLayer('https://mt1.google.com/vt/lyrs=m@221097413,traffic,transit,bike&x={x}&y={y}&z={z}', {
                maxZoom: 20,
                attribution: '© Google Maps'
            });

            googleStreets.addTo(map);

            const baseMaps = {
                "Standart Harita": googleStreets,
                "Uydu Görünümü": googleHybrid,
                "Trafik Yoğunluğu": googleTraffic
            };

            L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
        }

        function createIcon(vehicle) {
            let statusClass = 'pulse-stopped'; // Varsayılan Kırmızı
            if (vehicle.Speed > 0) {
                statusClass = 'pulse-moving'; // Hareketli (Mavi/Turkuaz)
            } else if (vehicle.ACC) {
                statusClass = 'pulse-idle'; // Hız 0 ama Kontak Açık (Mor)
            }
            return L.divIcon({
                className: 'custom-vehicle-marker',
                html: `<div class="pulse-icon ${statusClass}"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });
        }

        function getPopupHTML(vehicle, addressHtml = 'Adres yükleniyor...') {
            let speedColor = '#ef4444';
            if (vehicle.Speed > 0) {
                speedColor = '#06b6d4';
            } else if (vehicle.ACC) {
                speedColor = '#a855f7';
            }
            const timeStr = vehicle.Datetime ? vehicle.Datetime : '-';

            return `
                <div style="font-family: sans-serif; min-width: 160px; padding: 2px;">
                    <div style="font-weight: 900; font-size: 16px; margin-bottom: 4px; color: #0f172a;">${vehicle.LicensePlate}</div>
                    <div style="color: ${speedColor}; font-size: 14px; font-weight: 900; margin-bottom: 4px;">${vehicle.Speed} km/h</div>
                    <div style="color: #64748b; font-size: 10px; margin-bottom: 2px;">Son Sinyal: <span style="font-weight:700; color:#334155;">${timeStr}</span></div>
                    <div class="address-field" style="color: #475569; font-size: 11px; margin-top: 6px; line-height: 1.3; border-top: 1px solid #e2e8f0; padding-top: 4px;">${addressHtml}</div>
                </div>
            `;
        }

        function updateMarkerAddressUI(marker, addressStr) {
            if (!marker.isPopupOpen()) return;
            const popupNode = marker.getPopup().getElement();
            if (popupNode) {
                const addrEl = popupNode.querySelector('.address-field');
                if (addrEl) addrEl.innerText = addressStr;
            }
        }

        function fetchAddressForMarker(marker, lat, lng) {
            const coordKey = lat.toFixed(4) + ',' + lng.toFixed(4);
            if (marker.lastAddressCoord === coordKey && marker.lastAddressStr) {
                updateMarkerAddressUI(marker, marker.lastAddressStr);
                return;
            }
            
            const now = Date.now();
            if (marker.lastAddressFetchTime && (now - marker.lastAddressFetchTime < 3000)) return; 
            marker.lastAddressFetchTime = now;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1&accept-language=tr`)
                .then(res => res.json())
                .then(data => {
                    let display = data.display_name;
                    if(data.address) {
                        const a = data.address;
                        const parts = [];
                        if(a.road) parts.push(a.road);
                        if(a.suburb) parts.push(a.suburb);
                        if(a.town || a.city) parts.push(a.town || a.city);
                        if(parts.length > 0) display = parts.join(', ');
                    }
                    marker.lastAddressCoord = coordKey;
                    marker.lastAddressStr = display;
                    updateMarkerAddressUI(marker, display);
                }).catch(() => {
                    updateMarkerAddressUI(marker, 'Adres alınamadı');
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

                    if (markers[vehicle.Node]) {
                        const marker = markers[vehicle.Node];
                        marker.setLatLng([lat, lng]);
                        marker.setIcon(createIcon(vehicle));
                        
                        if (marker.isPopupOpen()) {
                            const popupNode = marker.getPopup().getElement();
                            const addrEl = popupNode ? popupNode.querySelector('.address-field') : null;
                            const currentAddr = addrEl ? addrEl.innerText : 'Adres yükleniyor...';
                            marker.setPopupContent(getPopupHTML(vehicle, currentAddr));
                            fetchAddressForMarker(marker, lat, lng);
                        } else {
                            marker.setPopupContent(getPopupHTML(vehicle, marker.lastAddressStr || 'Adres yükleniyor...'));
                        }
                    } else {
                        const marker = L.marker([lat, lng], {
                            icon: createIcon(vehicle)
                        }).addTo(map);
                        
                        marker.bindPopup(getPopupHTML(vehicle, 'Adres yükleniyor...'));
                        marker.bindTooltip(vehicle.LicensePlate, {
                            permanent: true, direction: 'bottom', className: 'custom-vehicle-tooltip', offset: [0, 10]
                        });
                        
                        marker.on('popupopen', function() {
                            const currentLatLng = marker.getLatLng();
                            fetchAddressForMarker(marker, currentLatLng.lat, currentLatLng.lng);
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
            fetch('{{ route("vehicle-tracking.live") }}?_t=' + Date.now())
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
