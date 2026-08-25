<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filomerkez - Canlı Araç Takip Paneli</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
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
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            font-weight: 900 !important;
            color: #0f172a !important;
            font-size: 13px !important;
            text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff, 0px 0px 8px rgba(255,255,255,0.8);
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

        /* Premium 3D Glassmorphism - All Panels */
        .premium-glass-panel {
            background: linear-gradient(145deg, rgba(30, 58, 138, 0.85), rgba(15, 23, 42, 0.95)) !important;
            backdrop-filter: blur(25px) !important;
            -webkit-backdrop-filter: blur(25px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05), 0 20px 50px rgba(0, 0, 0, 0.5) !important;
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

    <!-- Üst Yüzen Arama (Floating Navbar) -->
    <div id="topNavbar" class="absolute top-6 z-[900] flex flex-col items-center transition-all duration-500" style="left: 50%; transform: translateX(-50%); width: 450px; margin-left: 140px;">
        <!-- Arama Kutusu -->
        <div class="relative rounded-2xl w-full" id="globalSearchWrapper">
            <div class="bg-slate-900/90 backdrop-blur-2xl border border-white/20 rounded-2xl flex items-center px-5 h-14 w-full relative z-[1002] shadow-[0_15px_40px_rgba(0,0,0,0.5)] transition-all focus-within:shadow-[0_15px_40px_rgba(99,102,241,0.5)] focus-within:border-indigo-400/80">
                <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input id="globalVehicleSearch" type="text" autocomplete="off" placeholder="Plaka veya lokasyon ara..." class="bg-transparent border-none outline-none text-white font-bold text-sm ml-3 w-full placeholder-slate-400 focus:ring-0">
            </div>
            <!-- Arama Sonuçları Dropdown -->
            <ul id="globalSearchResults" class="absolute left-0 right-0 mt-3 bg-slate-900/95 backdrop-blur-3xl border border-white/10 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.7)] overflow-hidden hidden z-[1001] custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                <!-- JS ile Doldurulacak -->
            </ul>
        </div>
    </div>

    <!-- Sağ Üst: Butonlar -->
    <div id="topRightButtons" class="absolute top-6 right-6 z-[900] flex items-center gap-4 pointer-events-auto">
        @if(session('success'))
            <div class="bg-emerald-500/90 backdrop-blur-md text-white px-5 py-3 rounded-2xl font-black text-sm flex items-center gap-2 shadow-[0_10px_25px_rgba(16,185,129,0.5)] border border-emerald-400/50">
                ✅ {{ session('success') }}
            </div>
        @endif
        <!-- Panele Dön 3D Buton -->
        <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white px-5 py-3.5 rounded-2xl shadow-[0_10px_25px_rgba(79,70,229,0.5)] font-black text-sm flex items-center gap-2 border border-white/20 hover:-translate-y-1 transition-all duration-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
            Panele Dön
        </a>
    </div>

    <!-- Mavi Premium Full-Height Sidebar (Glassmorphism) -->
    <div id="blueSidebar" class="sidebar-hidden absolute top-4 bottom-4 left-4 z-[1000] flex flex-col overflow-hidden w-[280px] bg-slate-900/80 backdrop-blur-2xl border border-white/10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
        
        <!-- Üst Kısım: Logo -->
        <div style="height:90px; display:flex; align-items:center; padding:0 24px; border-bottom:1px solid rgba(255,255,255,0.05); flex-shrink:0;">
            <div style="position:relative; width:46px; height:46px; border-radius:16px; background:linear-gradient(135deg, #fff, #f8fafc); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 25px rgba(0,0,0,0.3); flex-shrink:0;">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <div style="position:absolute; right:-4px; top:-4px; width:14px; height:14px; background:#10b981; border-radius:50%; border:2px solid #1e1b4b; box-shadow:0 0 10px rgba(16,185,129,0.5);"></div>
            </div>
            <div style="margin-left:16px;">
                <h1 style="font-size:22px; font-weight:900; color:white; letter-spacing:-0.5px; line-height:1; margin:0 0 4px 0; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">FiloTakip</h1>
                <p style="font-size:10px; font-weight:800; color:rgba(199,210,254,0.9); text-transform:uppercase; letter-spacing:1px; margin:0;">Araç Takip Sistemi</p>
            </div>
        </div>

        <!-- Ana Menü -->
        <div style="flex:1; padding:28px 16px; display:flex; flex-direction:column; gap:8px; overflow-y:auto;" class="custom-scrollbar">
            
            <!-- Canlı Harita (Aktif) -->
            <button class="sidebar-item active" style="position:relative;" onclick="window.location.reload();">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <div class="item-text">
                    <span>Canlı Harita</span>
                    <span>Tüm filoyu anlık izle</span>
                </div>
            </button>

            <!-- Geçmiş İzleme -->
            <button class="sidebar-item" style="position:relative;" onclick="openHistoryPanel()">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="item-text">
                    <span>Geçmiş İzleme</span>
                    <span>Rota ve Video Playback</span>
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
    <div id="imeiModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md transition-all duration-300">
        <div class="relative w-full max-w-md rounded-[32px] bg-slate-900/90 shadow-[0_30px_60px_rgba(0,0,0,0.8)] border border-white/10 scale-95 opacity-0 transition-all duration-300 overflow-hidden" id="imeiModalContent">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/20 to-purple-500/20 pointer-events-none"></div>
            <form action="{{ route('vehicle-tracking.assign-imei') }}" method="POST" class="relative z-10">
                @csrf
                <input type="hidden" name="vehicle_id" id="modalVehicleId">
                
                <div class="p-8">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div class="h-14 w-14 rounded-2xl bg-indigo-500/20 flex items-center justify-center text-indigo-400 text-2xl border border-indigo-500/30 shadow-inner drop-shadow-md">📱</div>
                            <div>
                                <h3 class="text-xl font-black text-white text-shadow-sm">Cihaz Ekle</h3>
                                <p class="text-[10px] font-bold text-indigo-300 uppercase tracking-widest mt-1" id="modalVehiclePlate">Plaka</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeImeiModal()" class="h-10 w-10 rounded-xl bg-white/5 text-slate-400 hover:bg-rose-500/20 hover:text-rose-400 border border-transparent hover:border-rose-500/30 transition-all flex items-center justify-center group">
                            <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[11px] font-black uppercase tracking-widest text-slate-300 mb-2">Cihaz IMEI Numarası</label>
                            <input type="text" name="device_imei" id="modalDeviceImei" 
                                class="w-full rounded-2xl border border-white/10 bg-black/30 px-5 py-4 text-white font-mono text-base focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 transition-all outline-none shadow-inner"
                                placeholder="Örn: 353210110128749">
                            <p class="text-[10px] text-slate-400 mt-3 font-bold">GPS cihazının arkasındaki 15 haneli numarayı giriniz.</p>
                        </div>
                    </div>

                    <div class="mt-10">
                        <button type="submit" class="w-full py-4 rounded-2xl bg-indigo-500 hover:bg-indigo-400 text-white font-black text-sm uppercase tracking-widest hover:-translate-y-1 transition-all shadow-[0_10px_20px_rgba(99,102,241,0.4)] hover:shadow-[0_15px_25px_rgba(99,102,241,0.6)] border border-indigo-400">
                            Kaydet ve Bağla
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SOL ARAÇ DETAY PANELİ (AŞAMA 2 & 3 & 4) -->
    <!-- ========================================== -->
    <div id="advancedVehiclePanel" class="hidden absolute flex flex-col premium-glass-panel rounded-3xl overflow-hidden transition-all duration-500" style="left: 310px; top: 16px; bottom: 16px; width: 380px; z-index: 9999;">
        <!-- Başlık Kısmı (Plaka ve Butonlar) -->
        <div class="px-6 py-5 border-b border-white/10 flex justify-between items-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-transparent pointer-events-none"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="relative w-4 h-4 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full animate-ping opacity-50" id="advPanelStatusGlow"></div>
                    <div class="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)] transition-colors duration-500 relative z-10" id="advPanelStatusDot"></div>
                </div>
                <div>
                    <h2 class="text-lg font-black text-white tracking-wide text-shadow-sm" id="advPanelPlate">42 C 0051</h2>
                    <p class="text-[11px] font-bold text-indigo-300 uppercase tracking-widest mt-0.5" id="advPanelDriver">Şoför Seçilmedi</p>
                </div>
            </div>
            <div class="flex items-center gap-1 relative z-10">
                <button onclick="closeAdvancedVehiclePanel()" class="w-8 h-8 rounded-xl bg-white/5 hover:bg-rose-500/20 hover:text-rose-400 flex justify-center items-center text-slate-400 transition-all border border-transparent hover:border-rose-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <!-- Sekme Seçici (Dropdown) -->
        <div class="p-4 border-b border-white/10 bg-black/20">
            <div class="relative">
                <select id="advPanelTabSelect" onchange="switchAdvPanelTab(this.value)" class="w-full appearance-none bg-slate-800/50 border border-white/10 text-white py-3 pl-4 pr-10 rounded-xl text-sm font-bold shadow-inner focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer">
                    <option value="tab-realtime" class="bg-slate-800">Anlık Araç Kullanımı</option>
                    <option value="tab-history" class="bg-slate-800">Araç Geçmişi</option>
                    <option value="tab-alarms" class="bg-slate-800">Aracın Alarmları</option>
                    <option value="tab-commands" class="bg-slate-800">Araç Komutları</option>
                    <option value="tab-reports" class="bg-slate-800">Raporlar</option>
                    <option value="tab-config" class="bg-slate-800">Araç Konfigürasyonu</option>
                    <option value="tab-routes" class="bg-slate-800">Rota Listesi</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-indigo-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <!-- Sekme İçerikleri Konteyneri -->
        <div class="flex-1 overflow-y-auto custom-scrollbar relative">
            
            <!-- Tab 1: Anlık Araç Kullanımı (KPI KARTLARI) -->
            <div id="tab-realtime" class="p-5 flex flex-col h-full gap-5">
                
                <!-- Temel Bilgi (Hız ve Kontak) -->
                <div class="bg-white/5 backdrop-blur-md p-5 rounded-3xl shadow-[0_8px_32px_rgba(0,0,0,0.3)] border border-white/10 flex items-center justify-between relative overflow-hidden group">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-indigo-500/20 rounded-full blur-3xl group-hover:bg-indigo-500/30 transition-all duration-500"></div>
                    <div class="relative z-10">
                        <div class="text-[10px] font-black text-indigo-300 uppercase tracking-[0.2em] mb-1">Şu Anki Hız</div>
                        <div class="text-3xl font-black text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.3)]" id="advTabHiz">0 <span class="text-sm text-indigo-200">km/s</span></div>
                    </div>
                    <div class="text-right relative z-10">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Kontak</div>
                        <div class="text-sm font-black text-emerald-400 drop-shadow-[0_0_10px_rgba(52,211,153,0.5)]" id="advTabKontak">-</div>
                    </div>
                </div>
                
                <!-- Adres -->
                <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl shadow-inner border border-white/5 flex gap-3 items-start">
                    <div class="mt-0.5 text-indigo-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></div>
                    <div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Mevcut Konum</div>
                        <div class="text-xs font-bold text-slate-200 leading-relaxed" id="advTabAdres">Adres Yükleniyor...</div>
                    </div>
                </div>

                <!-- 3D Animasyonlu KPI Kartları -->
                <div class="grid grid-cols-2 gap-4 mt-1">
                    <!-- KM KPI -->
                    <div class="relative overflow-hidden premium-glass-panel rounded-3xl p-5 shadow-[0_15px_35px_rgba(37,99,235,0.4)] border border-blue-400/30 transform hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(37,99,235,0.6)] transition-all duration-300 group cursor-default">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-white/20 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <svg class="w-7 h-7 mb-4 drop-shadow-md" style="color: #93c5fd;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80" style="color: #bfdbfe;">Bugünkü KM</div>
                                <div class="text-xl font-black text-white tracking-tight drop-shadow-lg" id="advTabMesafe">0.0 km</div>
                            </div>
                        </div>
                    </div>

                    <!-- Hız KPI -->
                    <div class="relative overflow-hidden premium-glass-panel rounded-3xl p-5 shadow-[0_15px_35px_rgba(16,185,129,0.4)] border border-emerald-400/30 transform hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.6)] transition-all duration-300 group cursor-default">
                        <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-white/20 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
                        <div class="relative z-10 flex flex-col h-full justify-between">
                            <svg class="w-7 h-7 mb-4 drop-shadow-md" style="color: #6ee7b7;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80" style="color: #a7f3d0;">Maks. Hız</div>
                                <div class="text-xl font-black text-white tracking-tight drop-shadow-lg" id="advTabMaxHiz">-- km/s</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Elektrik & Kontak KPI (Geniş) -->
                    <div class="col-span-2 relative overflow-hidden bg-gradient-to-r from-slate-800/90 to-slate-900/90 backdrop-blur-xl rounded-3xl p-5 shadow-[0_15px_30px_rgba(0,0,0,0.5)] border border-slate-600/50 transform hover:-translate-y-1 hover:border-slate-500/80 transition-all duration-300 flex justify-between items-center group cursor-default">
                        <div class="absolute -left-10 top-0 w-32 h-32 bg-yellow-500/10 rounded-full blur-3xl group-hover:bg-yellow-500/20 transition-colors duration-700"></div>
                        <div class="relative z-10">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Araç Voltajı</div>
                            <div class="text-xl font-black text-white flex items-center gap-2 drop-shadow-md">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span id="advTabVoltage">12.4V</span>
                            </div>
                        </div>
                        <div class="text-right relative z-10">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">İlk Kontak (Bugün)</div>
                            <div class="text-lg font-black text-white drop-shadow-md" id="advTabFirstIgnition">08:30</div>
                        </div>
                    </div>
                </div>

                <!-- Sensör Barı (Bottom) -->
                <div class="bg-gradient-to-r from-red-600/90 to-rose-700/90 backdrop-blur-md rounded-2xl text-white flex justify-between items-center px-5 py-3.5 text-[11px] font-bold mt-auto transition-colors duration-500 shadow-[0_10px_20px_rgba(225,29,72,0.4)] border border-red-400/30" id="advTabSensorBar">
                    <div class="flex items-center gap-1.5" title="Yükseklik"><svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg> <span id="advTabAltitude" class="drop-shadow-sm">0 m</span></div>
                    <div class="flex items-center gap-1.5" title="Uydu Sayısı"><svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path></svg> <span id="advTabSatellites" class="drop-shadow-sm">0</span></div>
                    <div class="flex items-center gap-1.5" title="Kontak Durumu"><svg class="w-4 h-4 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg> <span id="advTabIgnitionStatus" class="drop-shadow-sm">-</span></div>
                </div>
            </div>

            <!-- Tab 2: Araç Geçmişi -->
            <div id="tab-history" class="hidden p-6 flex flex-col gap-6">
                <div class="flex flex-col gap-2 relative">
                    <div class="absolute -left-6 top-0 bottom-0 w-1 bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.8)]"></div>
                    <label class="text-[10px] font-black text-indigo-300 uppercase tracking-widest">Hızlı Tarih Filtresi</label>
                    <div class="relative group">
                        <select id="advHistoryFastFilter" onchange="toggleCustomDatesAdv()" class="w-full appearance-none bg-white/5 border border-white/10 text-white py-3.5 px-5 rounded-2xl text-sm font-bold shadow-inner focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all cursor-pointer backdrop-blur-md group-hover:bg-white/10">
                            <option value="last_1_hour" class="bg-slate-800 text-white">Son 1 Saat</option>
                            <option value="last_3_hours" class="bg-slate-800 text-white">Son 3 Saat</option>
                            <option value="today" class="bg-slate-800 text-white">Bugün</option>
                            <option value="yesterday" class="bg-slate-800 text-white">Dün</option>
                            <option value="last_3_days" class="bg-slate-800 text-white">Son 3 Gün</option>
                            <option value="custom" class="bg-slate-800 text-white">Detaylı Aralık</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-indigo-400 group-hover:text-indigo-300 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>
                
                <div id="advHistoryCustomDates" class="hidden flex-col gap-4 p-5 bg-black/20 rounded-2xl border border-white/5 shadow-inner">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Başlangıç Tarihi</label>
                        <input type="datetime-local" id="advHistoryStart" class="w-full bg-slate-900/50 border border-white/10 text-white py-3 px-4 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 block">Bitiş Tarihi</label>
                        <input type="datetime-local" id="advHistoryEnd" class="w-full bg-slate-900/50 border border-white/10 text-white py-3 px-4 rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                </div>

                <button onclick="startAdvancedHistoryPlayback()" class="relative w-full mt-2 group overflow-hidden rounded-2xl">
                    <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-teal-500 transition-transform duration-500 group-hover:scale-105"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-emerald-400 to-teal-400 opacity-0 group-hover:opacity-100 transition-opacity duration-500 blur-md"></div>
                    <div class="relative px-6 py-4 flex items-center justify-center gap-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-black text-white text-sm tracking-widest uppercase text-shadow-sm">Geçmişi Getir</span>
                    </div>
                </button>
            </div>

            <!-- Diğer Tablar (Yakında) -->
            <div id="tab-other" class="hidden p-8 text-center text-slate-400 text-sm font-medium flex flex-col items-center justify-center min-h-[300px]">
                <div class="w-16 h-16 bg-white/5 rounded-2xl flex items-center justify-center mb-4 border border-white/10 shadow-inner">
                    <svg class="w-8 h-8 text-indigo-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                Bu modül arayüzü yakında aktif edilecektir.
            </div>
        </div>
        
        <!-- Geçmiş Özeti (History Summary - Sadece Geçmiş Modunda Açılır) -->
        <div id="advHistorySummary" class="hidden flex-1 overflow-y-auto bg-transparent p-6 flex flex-col gap-6 custom-scrollbar" style="min-height: 400px; max-height: calc(100vh - 280px);">
            <div class="relative overflow-hidden bg-gradient-to-br from-indigo-500/20 to-purple-500/20 p-5 rounded-3xl border border-indigo-500/30 shadow-inner">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-indigo-500/30 rounded-full blur-2xl"></div>
                <p class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-1 relative z-10">Toplam Mesafe</p>
                <div class="text-4xl font-black text-white relative z-10 drop-shadow-md" id="advSumTotalKm">0.0<span class="text-sm text-indigo-200 font-bold ml-1">km</span></div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 pb-6 border-b border-white/10">
                <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center shadow-inner">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Ortalama Hız</p>
                    <div class="text-2xl font-black text-white drop-shadow-md" id="advSumAvgSpd">0<span class="text-[10px] text-slate-400 font-bold ml-1">km/sa</span></div>
                </div>
                <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center shadow-inner">
                    <p class="text-[9px] font-black text-emerald-400/80 uppercase tracking-widest mb-1">Azami Hız</p>
                    <div class="text-2xl font-black text-emerald-400 drop-shadow-md" id="advSumMaxSpd">0<span class="text-[10px] text-emerald-400/60 font-bold ml-1">km/sa</span></div>
                </div>
            </div>
            
            <!-- Trip Özeti Detayları (Başlangıç/Bitiş) -->
            <!-- Trip Özeti Detayları (Başlangıç/Bitiş) -->
            <div class="flex flex-col gap-4 relative pl-4 border-l-2 border-dashed border-white/20 hidden" id="advSumRouteDetails">
                <!-- JS ile Doldurulacak -->
            </div>

            <!-- Oynatma Sırasında Canlı Gösterge (Speedometer & Odometer) -->
            <div id="playbackLiveGauges" class="flex flex-col items-center justify-center my-2 relative">
                <!-- Speedometer -->
                <div class="relative w-48 h-24 overflow-hidden mb-4">
                    <svg viewBox="0 0 100 50" class="w-full h-full overflow-visible drop-shadow-[0_0_15px_rgba(59,130,246,0.3)]">
                        <!-- Arka plan yayı (Gri) -->
                        <path d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="12" stroke-linecap="round" />
                        <!-- Dolgu yayı (Renkli) -->
                        <path id="speedGaugePath" d="M 10 50 A 40 40 0 0 1 90 50" fill="none" stroke="url(#speedGradient)" stroke-width="12" stroke-linecap="round" stroke-dasharray="125.6" stroke-dashoffset="125.6" class="transition-all duration-500 ease-out" />
                        
                        <defs>
                            <linearGradient id="speedGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#3b82f6" />
                                <stop offset="50%" stop-color="#10b981" />
                                <stop offset="100%" stop-color="#ef4444" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute bottom-0 left-0 right-0 text-center flex flex-col items-center">
                        <div class="text-3xl font-black text-white drop-shadow-[0_0_15px_rgba(255,255,255,0.5)] leading-none" id="liveSpeedValue">0</div>
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mt-1">km/sa</div>
                    </div>
                </div>

                <!-- Odometer -->
                <div class="bg-black/40 border border-white/10 p-2 rounded-xl flex gap-1 shadow-inner relative z-10">
                    <div class="bg-slate-800 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded border border-white/5" id="odo_1">0</div>
                    <div class="bg-slate-800 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded border border-white/5" id="odo_2">0</div>
                    <div class="bg-slate-800 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded border border-white/5" id="odo_3">0</div>
                    <div class="bg-slate-800 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded border border-white/5" id="odo_4">0</div>
                    <div class="bg-slate-800 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded border border-white/5" id="odo_5">0</div>
                    <div class="text-white font-black text-lg self-end pb-0.5 mx-0.5">,</div>
                    <div class="bg-indigo-600 text-white font-mono font-bold text-lg w-6 h-8 flex items-center justify-center rounded shadow-[0_0_10px_rgba(79,70,229,0.5)] border border-indigo-400/50" id="odo_6">0</div>
                    <div class="text-[9px] font-bold text-slate-400 self-end pb-1 ml-1">km</div>
                </div>
                
                <div class="text-xs font-bold text-slate-300 mt-4 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.8)]"></div>
                    Hareket Süresi: <span id="liveDurationValue" class="font-mono font-black text-indigo-300 drop-shadow-md">00:00:00</span>
                </div>
            </div>
            
            <button onclick="document.getElementById('rightHistoryPanel').style.transform = 'translateX(0)';" class="mt-2 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-xs font-black text-indigo-400 hover:text-indigo-300 text-center w-full transition-colors border border-white/5">SEFER LİSTESİNİ GÖR</button>
            
            <!-- Pasta Grafik -->
            <div class="mt-auto pt-6 border-t border-white/10 flex items-center justify-between">
                <div class="w-24 h-24 rounded-full shadow-[0_0_20px_rgba(0,0,0,0.5)] border-4 border-slate-800" id="advSumPieChart" style="background: conic-gradient(#ef4444 0% 0%, #3b82f6 0% 0%, #f43f5e 0% 0%);"></div>
                <div class="flex flex-col gap-3 text-[10px] font-bold">
                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.8)]"></div> <span class="text-white w-14">Hareketli</span> <span id="advSumMovPct" class="text-blue-300 font-black">-%</span></div>
                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]"></div> <span class="text-white w-14">Duran</span> <span id="advSumStpPct" class="text-red-300 font-black">-%</span></div>
                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-full bg-purple-500 shadow-[0_0_10px_rgba(168,85,247,0.8)]"></div> <span class="text-white w-14">Rölanti</span> <span id="advSumIdlPct" class="text-purple-300 font-black">-%</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SAĞ GEÇMİŞ KAYITLARI PANELİ (AŞAMA 5) -->
    <!-- ========================================== -->
    <div id="rightHistoryPanel" class="fixed z-[9999] flex flex-col premium-glass-panel rounded-3xl overflow-hidden transition-transform duration-500" style="top: 16px; bottom: 16px; right: 16px; width: 400px; transform: translateX(120%);">
        <!-- Başlık -->
        <div class="px-6 py-5 border-b border-white/10 flex justify-between items-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-l from-indigo-500/10 to-transparent pointer-events-none"></div>
            <div class="flex items-center gap-4 relative z-10">
                <button onclick="document.getElementById('rightHistoryPanel').style.transform = 'translateX(120%)';" class="w-8 h-8 rounded-xl bg-white/5 hover:bg-slate-700 flex justify-center items-center text-slate-400 hover:text-white transition-all border border-transparent hover:border-white/20 mr-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </button>
                <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center border border-indigo-500/30 shadow-[0_0_15px_rgba(99,102,241,0.2)]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <h2 class="text-lg font-black text-white tracking-wide text-shadow-sm">Zaman Çizelgesi</h2>
                    <p class="text-[10px] font-bold text-indigo-300 uppercase tracking-widest mt-0.5">Geçmiş Kayıtları</p>
                </div>
            </div>
            <div class="text-xs font-black text-white bg-indigo-600/80 px-4 py-1.5 rounded-full shadow-[0_0_15px_rgba(79,70,229,0.5)] border border-indigo-400/50 relative z-10">
                <span id="advHistoryCount">0</span> Kayıt
            </div>
        </div>
        
        <!-- Arama / Filtre -->
        <div class="p-4 border-b border-white/10 bg-black/20">
            <div class="relative">
                <svg class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" placeholder="Adres veya saat ara..." class="w-full pl-11 pr-4 py-3 bg-slate-800/50 border border-white/10 rounded-xl text-xs font-bold text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 transition-all shadow-inner">
            </div>
        </div>

        <!-- Liste -->
        <div class="flex-1 overflow-y-auto bg-transparent p-5 flex flex-col gap-0 custom-scrollbar relative" id="advHistoryListContainer">
            <!-- Örnek Eleman (JS ile Doldurulacak) -->
            <div class="text-center text-slate-400 text-xs font-bold mt-10">
                Geçmiş kaydı oluşturulduğunda noktalar burada listelenecektir.
            </div>
        </div>
    </div>
    <div id="arventoTopBar" class="hidden absolute w-[700px] left-1/2 -translate-x-1/2 premium-glass-panel rounded-full px-4 py-2 flex items-center gap-3 transition-all" style="top: 80px; margin-left: 140px; z-index: 9999;">
        <!-- Kapat Butonu -->
        <button onclick="exitHistoryMode()" class="w-12 h-12 rounded-full bg-white/5 hover:bg-rose-500/20 flex items-center justify-center text-slate-300 hover:text-rose-400 hover:border-rose-500/50 border border-white/10 transition-all shadow-inner group shrink-0">
            <svg class="w-6 h-6 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </button>

        <div class="font-black text-white text-base tracking-widest uppercase ml-1 mr-2 text-shadow-sm"><div class="w-2 h-2 rounded-full bg-indigo-500 inline-block mr-2 shadow-[0_0_8px_rgba(99,102,241,0.8)]"></div>Geçmiş</div>

        <!-- Tarih Seçici -->
        <div class="flex-1 relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <select id="arventoDateSelect" onchange="checkCustomDateFilter()" class="block w-full pl-10 pr-4 py-3 text-xs font-bold text-white bg-black/30 border border-white/5 rounded-full appearance-none outline-none focus:ring-2 focus:ring-indigo-500 shadow-inner cursor-pointer backdrop-blur-md">
                <option value="today" class="bg-slate-800 text-white">Bugün</option>
                <option value="yesterday" class="bg-slate-800 text-white">Dün</option>
                <option value="last_1_hour" class="bg-slate-800 text-white">Son 1 Saat</option>
                <option value="last_3_hours" class="bg-slate-800 text-white">Son 3 Saat</option>
                <option value="last_3_days" class="bg-slate-800 text-white">Son 3 Gün</option>
                <option value="custom" class="bg-slate-800 text-white">Detaylı Zaman Aralığı</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
        </div>

        <!-- Araç Seçici (Arama Özellikli) -->
        <div class="flex-1 relative" id="vehicleSearchContainer">
            <input type="hidden" id="arventoVehicleSelect" value="">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="vehicleSearchInput" onkeyup="filterVehicles()" onclick="toggleVehicleDropdown(event)" placeholder="Plaka Ara..." class="block w-full pl-10 pr-4 py-3 text-xs font-bold text-white bg-black/30 border border-white/5 rounded-full outline-none focus:ring-2 focus:ring-indigo-500 shadow-inner transition-all placeholder:font-bold placeholder:text-slate-500" autocomplete="off">
            </div>
            
            <!-- Açılır Liste -->
            <ul id="vehicleDropdownList" class="hidden absolute left-0 right-0 mt-3 bg-slate-800/95 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-[0_15px_40px_rgba(0,0,0,0.6)] max-h-60 overflow-y-auto custom-scrollbar z-[10005] p-2">
                @foreach($vehicles as $v)
                    @if($v->device_imei)
                    <li class="vehicle-option px-4 py-3 text-xs font-bold text-slate-300 hover:bg-indigo-500 hover:text-white rounded-xl cursor-pointer transition-all flex items-center gap-3" onclick="selectVehicle('{{ $v->node }}', '{{ $v->plate }}')">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                        {{ $v->plate }}
                    </li>
                    @endif
                @endforeach
                <li id="noVehicleResult" class="hidden px-4 py-3 text-xs font-bold text-slate-500 text-center">Sonuç bulunamadı</li>
            </ul>
        </div>

        <button onclick="fetchHistoryData()" class="px-6 py-3 rounded-full bg-indigo-500 hover:bg-indigo-400 text-white font-black text-xs tracking-widest uppercase transition-all shadow-[0_0_20px_rgba(99,102,241,0.5)] hover:scale-105 hover:shadow-[0_0_25px_rgba(99,102,241,0.7)] shrink-0">Getir</button>
    </div>

    <!-- Detaylı Tarih Seçici Modalı (Gizli) -->
    <div id="customDatePanel" class="hidden absolute w-80 premium-glass-panel rounded-3xl p-6 transition-all" style="top: 150px; left: 50%; transform: translateX(-50%); margin-left: 140px; z-index: 9999;">
        <h4 class="font-black text-white text-sm mb-4 tracking-wide text-shadow-sm flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.8)]"></div>Özel Tarih Aralığı</h4>
        <div class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Başlangıç</label>
                <input type="datetime-local" id="historyStartDate" class="w-full bg-black/30 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-inner">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Bitiş</label>
                <input type="datetime-local" id="historyEndDate" class="w-full bg-black/30 border border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-inner">
            </div>
        </div>
    </div>

    <!-- Özet ve Sefer Listesi Paneli (Sol Tarafta Floating) -->
    <div id="arventoTripPanel" class="hidden absolute w-80 max-h-[70vh] flex flex-col premium-glass-panel rounded-3xl overflow-hidden transition-all" style="left: 300px; top: 80px; z-index: 9999;">
        <!-- Özet Kartı -->
        <div class="relative overflow-hidden bg-gradient-to-br from-indigo-600/80 to-purple-700/80 p-6 border-b border-white/10">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/20 rounded-full blur-3xl"></div>
            <div class="text-[10px] font-black text-indigo-200 uppercase tracking-[0.2em] mb-2 relative z-10">Toplam Günlük Özet</div>
            <div class="text-4xl font-black text-white mb-6 drop-shadow-md relative z-10" id="summaryTotalDistance">0 km</div>
            
            <div class="grid grid-cols-2 gap-4 text-sm relative z-10">
                <div class="bg-black/20 p-3 rounded-xl border border-white/10 shadow-inner">
                    <div class="text-indigo-300 text-[9px] font-black uppercase tracking-widest">Maks Hız</div>
                    <div class="font-black text-white" id="summaryMaxSpeed">0 km/s</div>
                </div>
                <div class="bg-black/20 p-3 rounded-xl border border-white/10 shadow-inner">
                    <div class="text-indigo-300 text-[9px] font-black uppercase tracking-widest">Ortalama Hız</div>
                    <div class="font-black text-white" id="summaryAvgSpeed">0 km/s</div>
                </div>
            </div>
        </div>
        
        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between bg-black/20 backdrop-blur-md">
            <span class="font-black text-white text-sm">Sefer Listesi</span>
            <span class="text-[10px] font-black text-indigo-100 bg-indigo-500/50 border border-indigo-400/50 px-3 py-1.5 rounded-full shadow-[0_0_10px_rgba(99,102,241,0.4)]" id="summaryTripCount">0 Sefer</span>
        </div>
        
        <!-- Liste -->
        <div id="tripListContainer" class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar bg-transparent">
            <!-- Sefer öğeleri buraya JS ile basılacak -->
        </div>
    </div>

    <!-- Hız ve Oynatma Butonları (Sağ Alt Köşe) -->
    <div id="arventoPlayerControls" class="hidden absolute right-6 flex flex-col items-center gap-3 transition-all" style="bottom: 40px; z-index: 9999;">
        <!-- Hız Çarpanları -->
        <div class="flex flex-col-reverse gap-2" id="speedMultipliers">
            <button onclick="setPlaybackSpeed(100)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">100x</button>
            <button onclick="setPlaybackSpeed(75)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">75x</button>
            <button onclick="setPlaybackSpeed(50)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">50x</button>
            <button onclick="setPlaybackSpeed(25)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">25x</button>
            <button onclick="setPlaybackSpeed(10)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">10x</button>
            <button onclick="setPlaybackSpeed(5)" class="w-12 h-12 rounded-2xl bg-white/5 backdrop-blur-xl shadow-[0_0_15px_rgba(0,0,0,0.3)] border border-white/10 text-[10px] font-black text-slate-400 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all speed-btn">5x</button>
            <button onclick="setPlaybackSpeed(1)" class="w-14 h-14 rounded-2xl bg-indigo-500 shadow-[0_0_20px_rgba(99,102,241,0.6)] border border-indigo-400 text-[11px] font-black text-white hover:scale-105 transition-all speed-btn active-speed relative overflow-hidden"><div class="absolute inset-0 bg-white/20 opacity-0 hover:opacity-100 transition-opacity"></div>1x</button>
        </div>
    </div>

    <!-- Oynatıcı Slider ve Durdur Butonları (Orta Alt) -->
    <div id="arventoSliderContainer" class="hidden absolute premium-glass-panel rounded-3xl p-4 flex items-center gap-5 transition-all" style="bottom: 40px; left: 50%; transform: translateX(-50%); margin-left: 140px; width: 600px; max-width: 80%; z-index: 9999;">
        <div class="flex items-center gap-3 shrink-0">
            <button onclick="togglePlayback()" id="playPauseBtn" class="w-14 h-14 rounded-2xl bg-indigo-500 flex items-center justify-center text-white shadow-[0_0_20px_rgba(99,102,241,0.5)] border border-indigo-400 hover:scale-105 transition-all group overflow-hidden relative">
                <div class="absolute inset-0 bg-white/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <svg class="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
            </button>
            
            <button onclick="stopPlayback()" class="w-14 h-14 rounded-2xl bg-white/5 flex items-center justify-center text-slate-300 hover:bg-rose-500/20 hover:text-rose-400 hover:border-rose-500/50 border border-white/10 shadow-[0_0_15px_rgba(0,0,0,0.3)] transition-all shrink-0">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"></path></svg>
            </button>
        </div>

        <div class="flex-1 flex flex-col justify-center px-2">
            <div class="flex justify-between text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">
                <span id="playerCurrentTime" class="text-indigo-300 drop-shadow-md">--:--</span>
                <span id="playerCurrentSpeed" class="text-emerald-400 drop-shadow-md">0 km/s</span>
            </div>
            <!-- Özelleştirilmiş Range Slider -->
            <div class="relative w-full h-2">
                <input type="range" id="playerSlider" min="0" max="100" value="0" class="w-full h-2 bg-white/10 rounded-full appearance-none cursor-pointer outline-none z-10 relative" style="background: linear-gradient(to right, #6366f1 0%, #6366f1 0%, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.1) 100%);">
            </div>
        </div>
    </div>

    <script>
        let map;
        let markers = {};
        let markerClusterGroup;
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

            // Kümeleme (Clustering) Grubunu Başlat
            markerClusterGroup = L.markerClusterGroup({
                disableClusteringAtZoom: 16, // Yaklaşınca kümeleri dağıt
                spiderfyOnMaxZoom: true,
                maxClusterRadius: 50
            });
            map.addLayer(markerClusterGroup);

            // Arayüz elemanları üzerindeki tıklamaların haritaya geçmesini engelle
            const uiElements = [
                'blueSidebar', 
                'globalSearchWrapper', 
                'topNavbar',
                'advancedVehiclePanel', 
                'rightHistoryPanel', 
                'arventoTripPanel', 
                'arventoPlayerControls',
                'imeiModal',
                'topRightButtons'
            ];
            uiElements.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    L.DomEvent.disableClickPropagation(el);
                    L.DomEvent.disableScrollPropagation(el);
                }
            });
        }

        function createIcon(vehicle) {
            let statusClass = 'pulse-stopped'; // Varsayılan Kırmızı
            let arrowColor = '#ef4444';
            
            if (vehicle.Speed > 0) {
                statusClass = 'pulse-moving'; // Hareketli (Mavi/Turkuaz)
                arrowColor = '#06b6d4';
            } else if (vehicle.ACC) {
                statusClass = 'pulse-idle'; // Hız 0 ama Kontak Açık (Mor)
                arrowColor = '#a855f7';
            }
            
            let course = vehicle.Course || 0;
            let arrowHtml = '';
            
            // Eğer araç hareket ediyorsa veya kontak açıksa yön okunu göster
            if (statusClass !== 'pulse-stopped') {
                arrowHtml = `<div style="position: absolute; top: -18px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 12px solid transparent; border-right: 12px solid transparent; border-bottom: 22px solid ${arrowColor}; drop-shadow(0 3px 4px rgba(0,0,0,0.4)); z-index: -1;"></div>`;
            }

            return L.divIcon({
                className: 'custom-vehicle-marker',
                html: `
                    <div style="position: relative; width: 18px; height: 18px; transform: rotate(${course}deg);">
                        ${arrowHtml}
                        <div class="pulse-icon ${statusClass}" style="position: absolute; inset: 0;"></div>
                    </div>
                `,
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
                        });
                        
                        marker.bindPopup(getPopupHTML(vehicle, 'Adres yükleniyor...'));
                        marker.bindTooltip(vehicle.LicensePlate, {
                            permanent: true, direction: 'bottom', className: 'custom-vehicle-tooltip', offset: [0, 10]
                        });
                        
                        marker.on('popupopen', function() {
                            const currentLatLng = marker.getLatLng();
                            fetchAddressForMarker(marker, currentLatLng.lat, currentLatLng.lng);
                        });

                        markers[vehicle.Node] = marker;
                        markerClusterGroup.addLayer(marker);
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

        let globalVehicles = []; // Smart search için araç listesi

        function fetchData() {
            if (isHistoryMode) return;
            fetch('{{ route("vehicle-tracking.live") }}?_t=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data.vehicles) {
                        globalVehicles = data.vehicles;
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
        // ==========================================
        // GEÇMİŞ İZLEME (HISTORY PLAYBACK) LOGIC
        // ==========================================
        // ==========================================
        // GEÇMİŞ İZLEME (HISTORY PLAYBACK) LOGIC (ARVENTO STYLE)
        // ==========================================
        let isHistoryMode = false;
        let historyData = [];
        let tripData = [];
        let historyPolyline = null;
        let historyMarker = null;
        let playbackInterval = null;
        let currentPlaybackIndex = 0;
        let playbackSpeed = 1; // 1x, 5x, 10x
        let isPlaying = false;
        let activeTripIndex = null;

        function openHistoryPanel() {
            document.getElementById('arventoTopBar').classList.remove('hidden');
        }

        function checkCustomDateFilter() {
            const val = document.getElementById('arventoDateSelect').value;
            if (val === 'custom') {
                document.getElementById('customDatePanel').classList.remove('hidden');
            } else {
                document.getElementById('customDatePanel').classList.add('hidden');
            }
        }

        // ==========================================
        // ARAÇ ARAMA DROPDOWN LOGIC
        // ==========================================
        function toggleVehicleDropdown(e) {
            e.stopPropagation();
            document.getElementById('vehicleDropdownList').classList.remove('hidden');
            document.getElementById('vehicleSearchInput').focus();
        }

        function filterVehicles() {
            const input = document.getElementById('vehicleSearchInput').value.toLowerCase();
            const options = document.querySelectorAll('.vehicle-option');
            let hasVisible = false;

            options.forEach(opt => {
                const text = opt.innerText.toLowerCase();
                if (text.includes(input)) {
                    opt.classList.remove('hidden');
                    hasVisible = true;
                } else {
                    opt.classList.add('hidden');
                }
            });

            if (!hasVisible) {
                document.getElementById('noVehicleResult').classList.remove('hidden');
            } else {
                document.getElementById('noVehicleResult').classList.add('hidden');
            }
        }

        function selectVehicle(id, plate) {
            document.getElementById('arventoVehicleSelect').value = id;
            document.getElementById('vehicleSearchInput').value = plate;
            document.getElementById('vehicleDropdownList').classList.add('hidden');
        }

        // Dropdown dışına tıklanınca kapatma
        document.addEventListener('click', function(e) {
            const container = document.getElementById('vehicleSearchContainer');
            if (container && !container.contains(e.target)) {
                const list = document.getElementById('vehicleDropdownList');
                if (list) list.classList.add('hidden');
            }
        });
        // ==========================================

        function fetchHistoryData() {
            const vehicleId = document.getElementById('arventoVehicleSelect').value;
            const dateFilter = document.getElementById('arventoDateSelect').value;
            const startDate = document.getElementById('historyStartDate').value;
            const endDate = document.getElementById('historyEndDate').value;

            if (!vehicleId) {
                alert('Lütfen bir araç seçiniz.');
                return;
            }

            if (dateFilter === 'custom' && (!startDate || !endDate)) {
                alert('Lütfen özel tarih aralığı için başlangıç ve bitiş tarihlerini giriniz.');
                return;
            }

            // Canlı takibi durdur
            isHistoryMode = true;
            document.getElementById('customDatePanel').classList.add('hidden');
            
            // Markerları temizle
            markerClusterGroup.clearLayers();
            for (let key in markers) {
                map.removeLayer(markers[key]);
            }
            markers = {};

            const url = `{{ url('/vehicle-tracking/history') }}?vehicle_id=${vehicleId}&date_filter=${dateFilter}&start_date=${startDate}&end_date=${endDate}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.history.length > 0) {
                        historyData = data.history;
                        tripData = data.trips || [];
                        initArventoPlaybackUI();
                    } else {
                        alert('Seçilen tarih aralığında araca ait konum verisi bulunamadı.');
                        exitHistoryMode();
                    }
                })
                .catch(err => {
                    console.error('Geçmiş veri çekilirken hata:', err);
                    alert('Geçmiş veri çekilirken hata oluştu.');
                    exitHistoryMode();
                });
        }

        function initArventoPlaybackUI() {
            document.getElementById('arventoTripPanel').classList.remove('hidden');
            document.getElementById('arventoPlayerControls').classList.remove('hidden');
            document.getElementById('arventoSliderContainer').classList.remove('hidden');
            document.getElementById('arventoSliderContainer').classList.add('flex');
            
            // Özet Kartı Hesaplama
            let totalKm = 0;
            let maxSpd = 0;
            let spdSum = 0;
            let tripCnt = tripData.length;

            tripData.forEach(t => {
                totalKm += (t.distance_km || 0);
                if (t.max_speed > maxSpd) maxSpd = t.max_speed;
                spdSum += (t.avg_speed || 0);
            });

            const avgSpd = tripCnt > 0 ? (spdSum / tripCnt).toFixed(1) : 0;

            document.getElementById('summaryTotalDistance').innerText = totalKm.toFixed(1) + ' km';
            document.getElementById('summaryMaxSpeed').innerText = maxSpd + ' km/s';
            document.getElementById('summaryAvgSpeed').innerText = avgSpd + ' km/s';
            document.getElementById('summaryTripCount').innerText = tripCnt + ' Sefer';

            renderTripList();

            // Tüm rotayı çiz
            const latlngs = historyData.map(loc => [parseFloat(loc.lat), parseFloat(loc.lng)]);
            if (historyPolyline) map.removeLayer(historyPolyline);
            historyPolyline = L.polyline(latlngs, {color: '#2563eb', weight: 5, opacity: 0.8}).addTo(map);
            map.fitBounds(historyPolyline.getBounds());

            // Slider
            const slider = document.getElementById('playerSlider');
            slider.max = historyData.length - 1;
            slider.value = 0;
            currentPlaybackIndex = 0;

            // Marker
            if (historyMarker) map.removeLayer(historyMarker);
            const firstLoc = historyData[0];
            historyMarker = L.marker([parseFloat(firstLoc.lat), parseFloat(firstLoc.lng)], {
                icon: createIcon({ Speed: firstLoc.speed, ACC: firstLoc.acc })
            }).addTo(map);
            
            updatePlayerUI();

            slider.addEventListener('input', function() {
                currentPlaybackIndex = parseInt(this.value);
                updateHistoryMarker();
                updatePlayerUI();
            });
        }

        function renderTripList() {
            const container = document.getElementById('tripListContainer');
            container.innerHTML = '';

            tripData.forEach((trip, index) => {
                const durationMins = Math.floor(trip.duration_seconds / 60);
                const html = `
                    <div class="bg-white border border-slate-200 rounded-2xl p-3 shadow-sm hover:shadow-md transition-all cursor-pointer" onclick="playTrip(${index})">
                        <div class="flex items-start gap-3">
                            <div class="flex flex-col items-center mt-1">
                                <div class="w-3 h-3 rounded-full border-2 border-indigo-600 bg-white"></div>
                                <div class="w-0.5 h-10 bg-slate-200 my-1"></div>
                                <div class="w-3 h-3 rounded-full bg-indigo-600 border-2 border-indigo-200"></div>
                            </div>
                            <div class="flex-1">
                                <div class="text-[10px] text-slate-400 font-bold">${trip.start_time}</div>
                                <div class="text-xs font-bold text-slate-700 truncate w-48">${trip.start_lat}, ${trip.start_lng}</div>
                                
                                <div class="flex items-center gap-3 my-2 text-[10px] font-black text-slate-500">
                                    <span class="flex items-center gap-1 text-indigo-600"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> ${durationMins}dk</span>
                                    <span>${(trip.distance_km || 0).toFixed(1)} km</span>
                                    <span>Maks: ${trip.max_speed}</span>
                                </div>

                                <div class="text-[10px] text-slate-400 font-bold">${trip.end_time}</div>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', html);
            });
        }

        function playTrip(tripIndex) {
            const trip = tripData[tripIndex];
            // Find start index in historyData
            const startIndex = historyData.findIndex(h => h.timestamp === trip.start_timestamp);
            if (startIndex !== -1) {
                stopPlayback();
                currentPlaybackIndex = startIndex;
                document.getElementById('playerSlider').value = currentPlaybackIndex;
                updateHistoryMarker();
                updatePlayerUI();
                
                // Odaklan
                map.flyTo([trip.start_lat, trip.start_lng], 16, {duration: 1.5});
                setTimeout(() => { togglePlayback(); }, 1500);
            }
        }

        function setPlaybackSpeed(speed) {
            playbackSpeed = speed;
            document.querySelectorAll('.speed-btn').forEach(btn => {
                btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-indigo-600/40', 'active-speed');
                btn.classList.add('bg-white/90', 'text-slate-600');
            });
            event.target.classList.remove('bg-white/90', 'text-slate-600');
            event.target.classList.add('bg-indigo-600', 'text-white', 'shadow-indigo-600/40', 'active-speed');

            if (isPlaying) {
                clearInterval(playbackInterval);
                playbackInterval = setInterval(playNextFrame, 1000 / playbackSpeed);
            }
        }

        function togglePlayback() {
            const btn = document.getElementById('playPauseBtn');
            if (isPlaying) {
                clearInterval(playbackInterval);
                isPlaying = false;
                btn.innerHTML = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>'; // Play
            } else {
                isPlaying = true;
                btn.innerHTML = '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'; // Pause
                
                if (currentPlaybackIndex >= historyData.length - 1) {
                    currentPlaybackIndex = 0;
                }
                playbackInterval = setInterval(playNextFrame, 1000 / playbackSpeed);
            }
        }

        function stopPlayback() {
            if (isPlaying) togglePlayback();
            currentPlaybackIndex = 0;
            document.getElementById('playerSlider').value = 0;
            updateHistoryMarker();
            updatePlayerUI();
            map.fitBounds(historyPolyline.getBounds());
        }

        function playNextFrame() {
            if (currentPlaybackIndex < historyData.length - 1) {
                currentPlaybackIndex++;
                document.getElementById('playerSlider').value = currentPlaybackIndex;
                updateHistoryMarker();
                updatePlayerUI();
            } else {
                togglePlayback();
            }
        }

        function updateHistoryMarker() {
            const loc = historyData[currentPlaybackIndex];
            historyMarker.setLatLng([parseFloat(loc.lat), parseFloat(loc.lng)]);
            historyMarker.setIcon(createIcon({ Speed: loc.speed, ACC: loc.acc }));
            
            if (!map.getBounds().contains(historyMarker.getLatLng())) {
                map.panTo(historyMarker.getLatLng());
            }
        }

        function updatePlayerUI() {
            const loc = historyData[currentPlaybackIndex];
            document.getElementById('playerCurrentTime').innerText = loc.time;
            document.getElementById('playerCurrentSpeed').innerText = loc.speed + ' km/s';
            
            // Canlı Gösterge (Speedometer)
            const speed = parseInt(loc.speed) || 0;
            const speedEl = document.getElementById('liveSpeedValue');
            if(speedEl) {
                speedEl.innerText = speed;
                const maxSpeed = 160;
                const dashArray = 125.6; // SVG çember çevresi
                const offset = dashArray - (Math.min(speed, maxSpeed) / maxSpeed) * dashArray;
                document.getElementById('speedGaugePath').style.strokeDashoffset = offset;
                
                // Hareket Süresi (Tahmini - her nokta 15sn varsayımı)
                const durationSec = currentPlaybackIndex * 15;
                const h = Math.floor(durationSec / 3600).toString().padStart(2, '0');
                const m = Math.floor((durationSec % 3600) / 60).toString().padStart(2, '0');
                const s = (durationSec % 60).toString().padStart(2, '0');
                document.getElementById('liveDurationValue').innerText = `${h}:${m}:${s}`;
                
                // Odometer (Tahmini Gidilen Mesafe)
                if (!loc.cumDist) {
                    let dist = 0;
                    for(let i=1; i<=currentPlaybackIndex; i++){
                        dist += (historyData[i].speed / 3600) * 15;
                    }
                    loc.cumDist = dist;
                }
                const distStr = loc.cumDist.toFixed(1).padStart(7, '0').replace('.', '');
                for(let i=1; i<=6; i++) {
                    const el = document.getElementById('odo_' + i);
                    if (el) el.innerText = distStr[i-1] || '0';
                }
            }
        }

        function exitHistoryMode() {
            isHistoryMode = false;
            clearInterval(playbackInterval);
            isPlaying = false;
            
            document.getElementById('arventoTopBar').classList.add('hidden');
            document.getElementById('customDatePanel').classList.add('hidden');
            document.getElementById('arventoTripPanel').classList.add('hidden');
            document.getElementById('arventoPlayerControls').classList.add('hidden');
            document.getElementById('arventoSliderContainer').classList.add('hidden');
            document.getElementById('arventoSliderContainer').classList.remove('flex');
            
            if (historyPolyline) map.removeLayer(historyPolyline);
            if (historyMarker) map.removeLayer(historyMarker);
            
            historyData = [];
            tripData = [];
            currentPlaybackIndex = 0;
            
            window.location.reload();
        }

        // ==========================================
        // SMART SEARCH LOGIC (Navbar)
        // ==========================================
        const globalSearchInput = document.getElementById('globalVehicleSearch');
        const globalSearchResults = document.getElementById('globalSearchResults');

        document.addEventListener("DOMContentLoaded", function() {
            if (globalSearchInput) {
                globalSearchInput.addEventListener('input', function(e) {
                    const val = e.target.value.toLowerCase().trim();
                    globalSearchResults.innerHTML = '';
                    
                    if (val.length === 0) {
                        globalSearchResults.classList.add('hidden');
                        return;
                    }
                    
                    const results = globalVehicles.filter(v => v.LicensePlate && v.LicensePlate.toLowerCase().includes(val));
            
            if (results.length > 0) {
                globalSearchResults.classList.remove('hidden');
                results.forEach(vehicle => {
                    const li = document.createElement('li');
                    li.className = 'px-4 py-3 border-b border-white/5 hover:bg-indigo-500/20 cursor-pointer flex justify-between items-center transition-colors group';
                    
                    let dotColor = 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]';
                    if (vehicle.Speed > 0) dotColor = 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]';
                    else if (vehicle.ACC) dotColor = 'bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.8)]';
                    
                    li.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full ${dotColor}"></div>
                            <span class="font-bold text-slate-300 group-hover:text-white text-sm transition-colors">${vehicle.LicensePlate}</span>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-400 opacity-0 group-hover:opacity-100 transition-opacity">Seç</span>
                    `;
                    li.addEventListener('click', () => {
                        globalSearchInput.value = vehicle.LicensePlate;
                        globalSearchResults.classList.add('hidden');
                        
                        // Kamera odaklanması ve Gelişmiş Panelin açılması (Aşama 2)
                        focusOnVehicle(vehicle.Node);
                        openAdvancedVehiclePanel(vehicle);
                    });
                    globalSearchResults.appendChild(li);
                });
            } else {
                globalSearchResults.classList.remove('hidden');
                globalSearchResults.innerHTML = '<li class="px-4 py-3 text-xs font-bold text-slate-500 text-center uppercase tracking-widest">Araç bulunamadı</li>';
            }
        });
        }
    });

        document.addEventListener('click', function(e) {
            if (!document.getElementById('globalSearchWrapper').contains(e.target)) {
                globalSearchResults.classList.add('hidden');
            }
        });
        
        // ==========================================
        // ARAÇ DETAY PANELİ LOGIC (Aşama 2 & 3)
        // ==========================================
        let currentAdvVehicle = null;

        function openAdvancedVehiclePanel(vehicle) {
            currentAdvVehicle = vehicle;
            const panel = document.getElementById('advancedVehiclePanel');
            panel.classList.remove('hidden');
            
            // Başlık Güncelleme
            document.getElementById('advPanelPlate').innerText = vehicle.LicensePlate;
            document.getElementById('advPanelDriver').innerText = vehicle.DriverName || 'Şoför Seçilmedi';
            
            // Renk Güncelleme
            const dot = document.getElementById('advPanelStatusDot');
            dot.className = 'w-3 h-3 rounded-full shadow-sm transition-colors duration-500';
            let barColor = 'bg-red-600';
            if (vehicle.Speed > 0) {
                dot.classList.add('bg-cyan-500');
                barColor = 'bg-cyan-600';
            } else if (vehicle.ACC) {
                dot.classList.add('bg-purple-500');
                barColor = 'bg-purple-600';
            } else {
                dot.classList.add('bg-red-500');
            }
            document.getElementById('advTabSensorBar').className = `text-white flex justify-between items-center px-5 py-3 text-[11px] font-bold mt-auto transition-colors duration-500 ${barColor}`;
            
            // Tab 1 Verilerini Doldur
            const hizHtml = `${vehicle.Speed} <span class="text-xs text-slate-400 font-bold">km/s</span>`;
            document.getElementById('advTabHiz').innerHTML = hizHtml;
            document.getElementById('advTabMesafe').innerText = (vehicle.DailyDistance || '0.0') + ' km';
            // Kontak Durumu ve Rengi
            const kontakEl = document.getElementById('advTabKontak');
            const ignitionStatusEl = document.getElementById('advTabIgnitionStatus');
            
            if (vehicle.Speed > 0) {
                kontakEl.innerText = 'AÇIK';
                kontakEl.className = 'text-sm font-black drop-shadow-[0_0_10px_rgba(52,211,153,0.5)]';
                kontakEl.style.color = '#34d399'; // Emerald (Moving)
                ignitionStatusEl.innerText = 'Açık';
            } else if (vehicle.ACC) {
                // Hız 0 ama Kontak Açık. Voltaj 13.0V'dan düşükse motor çalışmıyordur.
                if (vehicle.Voltage && vehicle.Voltage < 13.0) {
                    kontakEl.innerText = 'KONTAK AÇIK';
                    kontakEl.className = 'text-sm font-black drop-shadow-[0_0_10px_rgba(168,85,247,0.5)]';
                    kontakEl.style.color = '#c084fc'; // Purple (Ignition On, Engine Off)
                    ignitionStatusEl.innerText = 'Akü (Motor Kapalı)';
                } else {
                    kontakEl.innerText = 'RÖLANTİ';
                    kontakEl.className = 'text-sm font-black drop-shadow-[0_0_10px_rgba(251,146,60,0.5)]';
                    kontakEl.style.color = '#fb923c'; // Orange (Idling)
                    ignitionStatusEl.innerText = 'Rölanti';
                }
            } else {
                kontakEl.innerText = 'KAPALI';
                kontakEl.className = 'text-sm font-black drop-shadow-[0_0_10px_rgba(251,113,133,0.5)]';
                kontakEl.style.color = '#fb7185'; // Rose (Off)
                ignitionStatusEl.innerText = 'Kapalı';
            }
            
            // Ekstra Sensör ve Veriler
            document.getElementById('advTabMaxHiz').innerText = (vehicle.MaxSpeed || vehicle.Speed || 0) + ' km/s';
            document.getElementById('advTabVoltage').innerText = (vehicle.Voltage || '12.4') + 'V'; // API'de yoksa mock
            document.getElementById('advTabFirstIgnition').innerText = vehicle.FirstIgnitionTime || '08:30'; // API'de yoksa mock
            
            // Altitude & Satellites (Eğer API'den geliyorsa)
            document.getElementById('advTabAltitude').innerText = (vehicle.Altitude || '0') + ' m';
            document.getElementById('advTabSatellites').innerText = vehicle.Satellites || '0';
            
            // Adres Getir
            if (vehicle.Latitude && vehicle.Longitude) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${vehicle.Latitude}&lon=${vehicle.Longitude}`)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('advTabAdres').innerText = data.display_name || 'Adres bulunamadı';
                    }).catch(() => {
                        document.getElementById('advTabAdres').innerText = 'Adres alınamadı';
                    });
            } else {
                document.getElementById('advTabAdres').innerText = '-';
            }
            
            // Sadece Anlık Görünümü Aç
            switchAdvPanelTab('tab-realtime');
            document.getElementById('advPanelTabSelect').value = 'tab-realtime';
        }

        function closeAdvancedVehiclePanel() {
            document.getElementById('advancedVehiclePanel').classList.add('hidden');
            currentAdvVehicle = null;
        }

        function switchAdvPanelTab(tabId) {
            // Önce tüm tabları gizle
            const tabs = ['tab-realtime', 'tab-history', 'tab-alarms', 'tab-other'];
            tabs.forEach(t => {
                const el = document.getElementById(t);
                if(el) el.classList.add('hidden');
            });
            document.getElementById('advHistorySummary').classList.add('hidden');
            
            // Seçileni aç (Eğer diğer falansa tab-other'a yönlendir)
            const el = document.getElementById(tabId);
            if(el) {
                el.classList.remove('hidden');
            } else {
                document.getElementById('tab-other').classList.remove('hidden');
            }
        }

        function toggleCustomDatesAdv() {
            const val = document.getElementById('advHistoryFastFilter').value;
            if (val === 'custom') {
                document.getElementById('advHistoryCustomDates').classList.remove('hidden');
                document.getElementById('advHistoryCustomDates').classList.add('flex');
            } else {
                document.getElementById('advHistoryCustomDates').classList.add('hidden');
                document.getElementById('advHistoryCustomDates').classList.remove('flex');
            }
        }
        
        function startAdvancedHistoryPlayback() {
            const vehicleId = currentAdvVehicle ? currentAdvVehicle.Node : null;
            if (!vehicleId) return alert('Lütfen bir araç seçiniz.');

            const fastFilter = document.getElementById('advHistoryFastFilter').value;
            let startDate = '', endDate = '';
            
            // Yerel saati düzeltmek için timezone offset'i çıkarıyoruz
            const tzoffset = (new Date()).getTimezoneOffset() * 60000; 
            const now = new Date(Date.now() - tzoffset);

            if (fastFilter === 'last_1_hour') {
                endDate = now.toISOString().slice(0,16);
                now.setHours(now.getHours() - 1);
                startDate = now.toISOString().slice(0,16);
            } else if (fastFilter === 'last_3_hours') {
                endDate = now.toISOString().slice(0,16);
                now.setHours(now.getHours() - 3);
                startDate = now.toISOString().slice(0,16);
            } else if (fastFilter === 'today') {
                endDate = now.toISOString().slice(0,16);
                now.setUTCHours(0,0,0,0);
                startDate = now.toISOString().slice(0,16);
            } else if (fastFilter === 'yesterday') {
                const yest = new Date(Date.now() - tzoffset);
                yest.setDate(yest.getDate() - 1);
                yest.setUTCHours(0,0,0,0);
                startDate = yest.toISOString().slice(0,16);
                yest.setUTCHours(23,59,59,999);
                endDate = yest.toISOString().slice(0,16);
            } else if (fastFilter === 'last_3_days') {
                endDate = now.toISOString().slice(0,16);
                now.setDate(now.getDate() - 3);
                startDate = now.toISOString().slice(0,16);
            } else if (fastFilter === 'custom') {
                startDate = document.getElementById('advHistoryStart').value;
                endDate = document.getElementById('advHistoryEnd').value;
            }

            if (!startDate || !endDate) return alert('Geçerli bir tarih aralığı bulunamadı.');

            isHistoryMode = true;
            
            markerClusterGroup.clearLayers();
            for (let key in markers) {
                map.removeLayer(markers[key]);
            }
            markers = {};

            const url = `{{ url('/vehicle-tracking/history') }}?vehicle_id=${vehicleId}&date_filter=custom&start_date=${startDate}&end_date=${endDate}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.history.length > 0) {
                        historyData = data.history;
                        tripData = data.trips || [];
                        initAdvancedPlaybackUI();
                    } else {
                        alert('Seçilen tarih aralığında veri bulunamadı.');
                        exitHistoryMode();
                    }
                })
                .catch(err => {
                    console.error('Geçmiş veri çekilirken hata:', err);
                    alert('Hata oluştu.');
                    exitHistoryMode();
                });
        }
        
        function initAdvancedPlaybackUI() {
            switchAdvPanelTab('advHistorySummary');
            document.getElementById('advHistorySummary').classList.remove('hidden');
            
            // Player UI
            document.getElementById('arventoPlayerControls').classList.remove('hidden');
            document.getElementById('arventoSliderContainer').classList.remove('hidden');
            document.getElementById('arventoSliderContainer').classList.add('flex');
            
            // Sağ Paneli Aç
            document.getElementById('rightHistoryPanel').style.transform = 'translateX(0)';
            document.getElementById('advHistoryCount').innerText = historyData.length;
            
            let totalKm = 0, maxSpd = 0, spdSum = 0, movSec = 0, idleSec = 0, stopSec = 0;
            const container = document.getElementById('advHistoryListContainer');
            container.innerHTML = '';
            
            // Başlangıç ve Bitiş Bilgilerini Doldur
            if (historyData.length > 0) {
                const firstLoc = historyData[0];
                const lastLoc = historyData[historyData.length - 1];
                const startAddr = firstLoc.lat + ', ' + firstLoc.lng;
                const endAddr = lastLoc.lat + ', ' + lastLoc.lng;
                
                const routeDiv = document.getElementById('advSumRouteDetails');
                routeDiv.classList.remove('hidden');
                routeDiv.innerHTML = `
                    <div class="relative">
                        <div class="absolute -left-[23px] top-1 w-3 h-3 rounded-full bg-orange-500 ring-4 ring-slate-900 shadow-[0_0_10px_rgba(249,115,22,0.8)]"></div>
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Başlangıç</div>
                        <div class="text-xs font-bold text-slate-200 line-clamp-2" id="routeStartAddr" title="${startAddr}">${startAddr}</div>
                        <div class="text-[10px] font-bold text-slate-500 mt-1">${firstLoc.time}</div>
                    </div>
                    <div class="relative mt-2">
                        <div class="absolute -left-[23px] top-1 w-3 h-3 rounded-full bg-amber-500 ring-4 ring-slate-900 shadow-[0_0_10px_rgba(245,158,11,0.8)]"></div>
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Bitiş</div>
                        <div class="text-xs font-bold text-slate-200 line-clamp-2" id="routeEndAddr" title="${endAddr}">${endAddr}</div>
                        <div class="text-[10px] font-bold text-slate-500 mt-1">${lastLoc.time}</div>
                    </div>
                `;
                
                // Fetch real addresses using Nominatim (arka planda çalışır, gecikme yapmaz)
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${firstLoc.lat}&lon=${firstLoc.lng}&zoom=18&addressdetails=1&accept-language=tr`)
                    .then(res => res.json()).then(data => { if(data.display_name) document.getElementById('routeStartAddr').innerText = data.display_name; });
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lastLoc.lat}&lon=${lastLoc.lng}&zoom=18&addressdetails=1&accept-language=tr`)
                    .then(res => res.json()).then(data => { if(data.display_name) document.getElementById('routeEndAddr').innerText = data.display_name; });
            }
            
            historyData.forEach((loc, idx) => {
                // Pie Chart verileri (Basit Tahmin: 0 hız stop, >0 mov)
                if(loc.speed > 0) movSec += 30; // varsayılan saniye
                else if(loc.acc) idleSec += 30;
                else stopSec += 30;
                
                // Sağ panele liste elemanı ekle (Zaman Çizelgesi / Timeline Modeli)
                const speedColor = loc.speed > 0 ? 'text-emerald-400 drop-shadow-[0_0_5px_rgba(52,211,153,0.8)]' : 'text-rose-400';
                const dotColor = loc.speed > 0 ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.8)]' : (loc.acc ? 'bg-indigo-500 shadow-[0_0_10px_rgba(99,102,241,0.8)]' : 'bg-rose-500 shadow-[0_0_10px_rgba(244,63,94,0.8)]');
                const lineColor = idx === historyData.length - 1 ? 'bg-transparent' : (loc.speed > 0 ? 'bg-emerald-500/30' : 'bg-rose-500/30');

                container.insertAdjacentHTML('beforeend', `
                    <div class="relative pl-7 py-3 cursor-pointer group hover:bg-white/5 transition-colors rounded-xl" onclick="playTripFromIndex(${idx})">
                        <!-- Timeline Çizgisi -->
                        <div class="absolute left-3 top-8 bottom-[-1rem] w-0.5 ${lineColor} rounded-full group-hover:bg-indigo-500/50 transition-colors"></div>
                        <!-- Timeline Noktası -->
                        <div class="absolute left-[7.5px] top-[18px] w-2.5 h-2.5 rounded-full ${dotColor} ring-4 ring-slate-900 group-hover:scale-125 transition-transform z-10"></div>
                        
                        <div class="flex items-start justify-between gap-3 relative z-10">
                            <div class="flex-1">
                                <div class="text-[10px] text-indigo-300/80 font-black tracking-widest mb-0.5">${loc.time}</div>
                                <div class="text-xs font-bold text-slate-200 line-clamp-1 group-hover:text-white transition-colors" title="${loc.lat}, ${loc.lng}">${loc.lat}, ${loc.lng}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-black ${speedColor}">${loc.speed}</div>
                                <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">km/s</div>
                            </div>
                        </div>
                    </div>
                `);
            });

            tripData.forEach(t => {
                totalKm += (t.distance_km || 0);
                if (t.max_speed > maxSpd) maxSpd = t.max_speed;
                spdSum += (t.avg_speed || 0);
            });
            const avgSpd = tripData.length > 0 ? (spdSum / tripData.length).toFixed(1) : 0;
            
            // Özet Panelini Güncelle
            document.getElementById('advSumTotalKm').innerHTML = `${totalKm.toFixed(1)}<span class="text-sm text-slate-500 font-bold ml-1">km</span>`;
            document.getElementById('advSumAvgSpd').innerHTML = `${avgSpd}<span class="text-xs text-slate-400 font-bold ml-1">km/sa</span>`;
            document.getElementById('advSumMaxSpd').innerHTML = `${maxSpd}<span class="text-xs text-slate-400 font-bold ml-1">km/sa</span>`;
            
            // Pasta Grafik Güncelle (Conic Gradient)
            const totalSec = movSec + stopSec + idleSec || 1;
            const movPct = Math.round((movSec / totalSec) * 100);
            const stopPct = Math.round((stopSec / totalSec) * 100);
            const idlePct = Math.round((idleSec / totalSec) * 100);
            
            document.getElementById('advSumMovPct').innerText = `%${movPct}`;
            document.getElementById('advSumStpPct').innerText = `%${stopPct}`;
            document.getElementById('advSumIdlPct').innerText = `%${idlePct}`;
            
            const p1 = movPct;
            const p2 = p1 + stopPct;
            document.getElementById('advSumPieChart').style.background = `conic-gradient(#3b82f6 0% ${p1}%, #ef4444 ${p1}% ${p2}%, #f472b6 ${p2}% 100%)`;
            
            // Haritada Çiz
            const latlngs = historyData.map(loc => [parseFloat(loc.lat), parseFloat(loc.lng)]);
            if (historyPolyline) map.removeLayer(historyPolyline);
            historyPolyline = L.polyline(latlngs, {color: '#6366f1', weight: 6, opacity: 0.9, lineCap: 'round'}).addTo(map);
            map.fitBounds(historyPolyline.getBounds());
            
            const slider = document.getElementById('playerSlider');
            slider.max = historyData.length - 1;
            slider.value = 0;
            currentPlaybackIndex = 0;
            
            if (historyMarker) map.removeLayer(historyMarker);
            const firstLoc = historyData[0];
            historyMarker = L.marker([parseFloat(firstLoc.lat), parseFloat(firstLoc.lng)], {
                icon: createIcon({ Speed: firstLoc.speed, ACC: firstLoc.acc, Course: firstLoc.course })
            }).addTo(map);
            
            updatePlayerUI();
            slider.addEventListener('input', function() {
                currentPlaybackIndex = parseInt(this.value);
                updateHistoryMarker();
                updatePlayerUI();
            });
        }
        
        function playTripFromIndex(idx) {
            currentPlaybackIndex = idx;
            document.getElementById('playerSlider').value = idx;
            updateHistoryMarker();
            updatePlayerUI();
            map.flyTo([historyData[idx].lat, historyData[idx].lng], 16, {duration: 1});
        }
    </script>
</body>
</html>
