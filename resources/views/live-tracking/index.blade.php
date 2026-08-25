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

    <!-- Arvento Tarzı Premium Geçmiş İzleme UI (Top Bar) -->
    <div id="arventoTopBar" class="hidden absolute w-[600px] left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur-md rounded-full shadow-2xl border border-slate-200 px-6 py-3 flex items-center gap-4 transition-all" style="top: 80px; margin-left: 140px; z-index: 9999;">
        <!-- Kapat Butonu -->
        <button onclick="exitHistoryMode()" class="w-10 h-10 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </button>

        <div class="font-black text-slate-800 text-lg mr-4">Geçmiş</div>

        <!-- Tarih Seçici -->
        <div class="flex-1 relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <select id="arventoDateSelect" onchange="checkCustomDateFilter()" class="block w-full pl-9 pr-3 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-full appearance-none outline-none focus:border-indigo-500 shadow-sm cursor-pointer">
                <option value="today">Bugün</option>
                <option value="yesterday">Dün</option>
                <option value="last_1_hour">Son 1 Saat</option>
                <option value="last_3_hours">Son 3 Saat</option>
                <option value="last_3_days">Son 3 Gün</option>
                <option value="custom">Detaylı Zaman Aralığı</option>
            </select>
        </div>

        <!-- Araç Seçici (Arama Özellikli) -->
        <div class="flex-1 relative" id="vehicleSearchContainer">
            <input type="hidden" id="arventoVehicleSelect" value="">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="vehicleSearchInput" onkeyup="filterVehicles()" onclick="toggleVehicleDropdown(event)" placeholder="Plaka Ara (Örn: 42 C)..." class="block w-full pl-9 pr-3 py-2 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-full outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 shadow-sm transition-all placeholder:font-normal placeholder:text-slate-400" autocomplete="off">
            </div>
            
            <!-- Açılır Liste -->
            <ul id="vehicleDropdownList" class="hidden absolute left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl border border-slate-200 rounded-2xl shadow-2xl max-h-60 overflow-y-auto custom-scrollbar z-[10005]">
                @foreach($vehicles as $v)
                    @if($v->device_imei)
                    <li class="vehicle-option px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-b border-slate-50 last:border-0 transition-all flex items-center gap-2" onclick="selectVehicle('{{ $v->id }}', '{{ $v->plate }}')">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div>
                        {{ $v->plate }}
                    </li>
                    @endif
                @endforeach
                <li id="noVehicleResult" class="hidden px-4 py-3 text-xs font-bold text-slate-400 text-center">Sonuç bulunamadı</li>
            </ul>
        </div>

        <button onclick="fetchHistoryData()" class="px-5 py-2 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm transition-all shadow-lg shadow-indigo-600/30">Getir</button>
    </div>

    <!-- Detaylı Tarih Seçici Modalı (Gizli) -->
    <div id="customDatePanel" class="hidden absolute w-80 bg-white/90 backdrop-blur-md rounded-2xl shadow-2xl border border-slate-200 p-5 transition-all" style="top: 150px; left: 50%; transform: translateX(-50%); margin-left: 140px; z-index: 9999;">
        <h4 class="font-black text-slate-800 text-sm mb-3">Özel Tarih Aralığı</h4>
        <div class="space-y-3">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Başlangıç</label>
                <input type="datetime-local" id="historyStartDate" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-slate-700 outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Bitiş</label>
                <input type="datetime-local" id="historyEndDate" class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold text-slate-700 outline-none">
            </div>
        </div>
    </div>

    <!-- Özet ve Sefer Listesi Paneli (Sol Tarafta Floating) -->
    <div id="arventoTripPanel" class="hidden absolute w-80 max-h-[70vh] flex flex-col bg-white/90 backdrop-blur-md rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transition-all" style="left: 300px; top: 80px; z-index: 9999;">
        <!-- Özet Kartı -->
        <div class="bg-indigo-600 p-5 text-white">
            <div class="text-xs font-bold text-indigo-200 uppercase tracking-widest mb-1">Toplam Günlük Özet</div>
            <div class="text-3xl font-black mb-4" id="summaryTotalDistance">0 km</div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-indigo-200 text-[10px] font-bold uppercase">Maks Hız</div>
                    <div class="font-black" id="summaryMaxSpeed">0 km/s</div>
                </div>
                <div>
                    <div class="text-indigo-200 text-[10px] font-bold uppercase">Ortalama Hız</div>
                    <div class="font-black" id="summaryAvgSpeed">0 km/s</div>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-white">
            <span class="font-black text-slate-800 text-sm">Sefer Listesi</span>
            <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg" id="summaryTripCount">0 Sefer</span>
        </div>
        
        <!-- Liste -->
        <div id="tripListContainer" class="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar bg-slate-50">
            <!-- Sefer öğeleri buraya JS ile basılacak -->
        </div>
    </div>

    <!-- Hız ve Oynatma Butonları (Sağ Alt Köşe) -->
    <div id="arventoPlayerControls" class="hidden absolute right-6 flex flex-col items-center gap-3 transition-all" style="bottom: 40px; z-index: 9999;">
        <!-- Hız Çarpanları -->
        <div class="flex flex-col-reverse gap-2" id="speedMultipliers">
            <button onclick="setPlaybackSpeed(100)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">100x</button>
            <button onclick="setPlaybackSpeed(75)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">75x</button>
            <button onclick="setPlaybackSpeed(50)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">50x</button>
            <button onclick="setPlaybackSpeed(25)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">25x</button>
            <button onclick="setPlaybackSpeed(10)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">10x</button>
            <button onclick="setPlaybackSpeed(5)" class="w-12 h-12 rounded-full bg-white/90 backdrop-blur-md shadow-xl border border-slate-100 text-xs font-black text-slate-600 hover:bg-indigo-600 hover:text-white transition-all speed-btn">5x</button>
            <button onclick="setPlaybackSpeed(1)" class="w-14 h-14 rounded-full bg-indigo-600 shadow-xl shadow-indigo-600/40 text-sm font-black text-white hover:scale-105 transition-all speed-btn active-speed">1x</button>
        </div>
    </div>

    <!-- Oynatıcı Slider ve Durdur Butonları (Sol Alt / Orta) -->
    <div id="arventoSliderContainer" class="hidden absolute right-[120px] bg-white/90 backdrop-blur-md rounded-full shadow-2xl border border-slate-200 p-3 flex items-center gap-4 transition-all" style="bottom: 40px; left: 304px; z-index: 9999;">
        <button onclick="togglePlayback()" id="playPauseBtn" class="w-12 h-12 rounded-full bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-600/30 hover:scale-105 transition-all shrink-0">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path></svg>
        </button>
        
        <button onclick="stopPlayback()" class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition-all shrink-0">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"></path></svg>
        </button>

        <div class="flex-1 flex flex-col justify-center px-4">
            <div class="flex justify-between text-[10px] font-bold text-slate-500 mb-1">
                <span id="playerCurrentTime">--:--</span>
                <span id="playerCurrentSpeed" class="text-indigo-600">0 km/s</span>
            </div>
            <input type="range" id="playerSlider" min="0" max="100" value="0" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer">
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

        function fetchData() {
            if (isHistoryMode) return;
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
    </script>
</body>
</html>
