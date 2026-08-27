<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filomerkez - Tanımlamalar</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
        
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
            text-decoration: none;
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
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-50 font-sans antialiased text-slate-800 flex">

    <!-- Mavi Premium Full-Height Sidebar -->
    <div id="blueSidebar" class="flex flex-col overflow-hidden shrink-0">
        
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
        <div style="flex:1; padding:28px 16px; display:flex; flex-direction:column; gap:8px; overflow-y:auto;">
            
            <a href="{{ route('vehicle-tracking.index') }}" class="sidebar-item" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <div class="item-text">
                    <span>Canlı Harita</span>
                    <span>Tüm filoyu anlık izle</span>
                </div>
            </a>

            <a href="{{ route('vehicle-tracking.definitions') }}" class="sidebar-item active" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <div class="item-text">
                    <span>Cihaz Tanımlamaları</span>
                    <span>Ayarlar ve konfigürasyon</span>
                </div>
            </a>

            <a href="{{ route('vehicle-tracking.reports') }}" class="sidebar-item" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <div class="item-text">
                    <span>Gelişmiş Raporlar</span>
                    <span>Geçmiş veri analizleri</span>
                </div>
            </a>

        </div>
    </div>

    <!-- Ana İçerik Alanı -->
    <div class="flex-1 flex flex-col h-full bg-slate-50 relative">
        
        <!-- Üst Bar -->
        <div class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0 shadow-sm z-10 relative">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Tanımlamalar Paneli</h2>
                <p class="text-xs font-bold text-slate-400 mt-0.5">Araç takip sistemi ayarları, alarmlar ve geofence konfigürasyonu</p>
            </div>
            <div class="flex items-center gap-3">
                @if(session('success'))
                    <div class="bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 border border-emerald-100">
                        ✅ {{ session('success') }}
                    </div>
                @endif
                <a href="{{ route('dashboard') }}" class="group relative flex items-center justify-center h-10 px-5 rounded-xl bg-white text-slate-700 font-bold text-sm transition-all duration-300 hover:shadow-lg border border-slate-200 hover:border-slate-300">
                    <svg class="w-4 h-4 mr-2 text-slate-400 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
                    Panele Dön
                </a>
            </div>
        </div>

        <!-- Sekmeler -->
        <div class="px-8 pt-6 pb-2 border-b border-slate-200 bg-white shrink-0 z-0">
            <div class="flex items-center gap-6">
                <button onclick="switchTab('vehicles')" id="tab-btn-vehicles" class="pb-3 text-sm font-black text-indigo-600 border-b-2 border-indigo-600 transition-colors">📡 Araç-Cihaz Eşleştirme</button>
                <button onclick="switchTab('alarms')" id="tab-btn-alarms" class="pb-3 text-sm font-black text-slate-400 border-b-2 border-transparent hover:text-slate-600 transition-colors">⚠️ Alarm Tanımları</button>
                <button onclick="switchTab('geofences')" id="tab-btn-geofences" class="pb-3 text-sm font-black text-slate-400 border-b-2 border-transparent hover:text-slate-600 transition-colors">🗺️ Bölge Tanımları</button>
                <button onclick="switchTab('schedules')" id="tab-btn-schedules" class="pb-3 text-sm font-black text-slate-400 border-b-2 border-transparent hover:text-slate-600 transition-colors">⏰ Çalışma Saatleri</button>
            </div>
        </div>

        <!-- İçerik Alanları -->
        <div class="flex-1 p-8 overflow-y-auto custom-scrollbar relative">
            
            <!-- SEKM: 1. ARAÇ-CİHAZ EŞLEŞTİRME -->
            <div id="tab-content-vehicles" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Cihaz Atamaları</h3>
                            <p class="text-xs font-bold text-slate-400 mt-1">Sistemde cihaz takılı {{ $vehiclesWithDevice->count() }} araç bulunuyor.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="fixFakeLocations()" class="px-4 py-2 bg-rose-50 text-rose-600 border border-rose-200 rounded-xl text-sm font-black hover:bg-rose-100 transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Hatalı Konumları Temizle
                            </button>
                            <button onclick="openImeiModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                Yeni Cihaz Tanımla
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @forelse($vehiclesWithDevice as $v)
                        <div class="p-5 rounded-2xl border border-indigo-100 bg-indigo-50/20 flex flex-col justify-between hover:shadow-lg transition-all duration-300 relative overflow-hidden group">
                            
                            <!-- Durum Göstergesi -->
                            @if($v->device_status === 'online')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    ONLINE
                                </div>
                            @elseif($v->device_status === 'offline')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-rose-100 text-rose-700 text-[10px] font-black shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    OFFLINE
                                </div>
                            @elseif($v->device_status === 'never')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black shadow-sm">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    SİNYAL YOK
                                </div>
                            @endif

                            <div>
                                <h4 class="text-base font-black text-slate-800">{{ $v->plate }}</h4>
                                <p class="text-xs font-bold text-slate-500 mt-1">{{ $v->brand }} {{ $v->model }}</p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100/50 flex items-center justify-between">
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Cihaz IMEI</span>
                                    <span class="text-sm font-black text-indigo-600 font-mono">{{ $v->device_imei }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="openAdminDeleteModal({{ $v->id }}, '{{ $v->plate }}')" class="h-8 w-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-rose-600 hover:border-rose-200 flex items-center justify-center transition-colors shadow-sm" title="Eşleştirmeyi Sil">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    <button type="button" onclick="openWizardEditMode({{ $v->id }}, '{{ $v->plate }}', '{{ $v->device_imei }}', '{{ $v->brand }}', '{{ $v->model }}', '{{ $v->model_year }}', '{{ $v->fuel_type }}')" class="h-8 w-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-200 flex items-center justify-center transition-colors shadow-sm" title="Düzenle">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-span-full py-12 flex flex-col items-center justify-center text-center">
                            <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-2xl flex items-center justify-center mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <h4 class="text-lg font-black text-slate-700">Kayıtlı Cihaz Yok</h4>
                            <p class="text-sm text-slate-500 mt-2 font-medium">Sistemde henüz eşleştirilmiş bir cihaz bulunmuyor. Sağ üstteki "Yeni Cihaz Tanımla" butonu ile başlayın.</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- SEKM: 2. ALARMLAR -->
            <div id="tab-content-alarms" class="hidden space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-black text-slate-800">Alarm Tanımları</h3>
                        <button onclick="openAlarmModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-black hover:bg-indigo-700 transition">
                            + Yeni Alarm Tanımla
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                    <th class="py-3 px-4">Araç</th>
                                    <th class="py-3 px-4">Alarm Tipi</th>
                                    <th class="py-3 px-4">Değer (Limit)</th>
                                    <th class="py-3 px-4">Bildirimler</th>
                                    <th class="py-3 px-4">Durum</th>
                                    <th class="py-3 px-4">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($alarms as $a)
                                <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                                    <td class="py-4 px-4">
                                        <span class="font-black text-slate-800">{{ $a->vehicle->plate }}</span>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-slate-600">{{ $a->alarm_type_label }}</td>
                                    <td class="py-4 px-4 font-bold text-indigo-600">
                                        @if($a->alarm_type === 'speed') {{ $a->threshold_value }} km/h
                                        @elseif($a->alarm_type === 'stop') {{ $a->threshold_value }} dk
                                        @else - @endif
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex gap-2 text-xs font-bold text-slate-500">
                                            @if($a->notify_email) <span class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md border border-blue-100">E-Posta</span> @endif
                                            @if($a->notify_sms) <span class="bg-green-50 text-green-600 px-2 py-0.5 rounded-md border border-green-100">SMS</span> @endif
                                            @if($a->notify_panel) <span class="bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-md border border-indigo-100">Panel</span> @endif
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <form action="{{ route('vehicle-tracking.alarms.toggle', $a) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="relative inline-flex items-center cursor-pointer">
                                                <div class="w-9 h-5 {{ $a->is_active ? 'bg-emerald-500' : 'bg-slate-300' }} rounded-full peer-focus:outline-none transition-colors"></div>
                                                <span class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform {{ $a->is_active ? 'translate-x-4' : '' }}"></span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-4 px-4">
                                        <form action="{{ route('vehicle-tracking.alarms.destroy', $a) }}" method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-rose-500 hover:text-rose-700 font-bold text-sm">Sil</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-sm font-bold text-slate-400">Henüz alarm tanımlanmamış.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SEKM: 3. GEOFENCES -->
            <div id="tab-content-geofences" class="hidden space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-800">Harita Üzerinden Bölge Tanımlama</h3>
                    <p class="text-sm font-bold text-slate-500 mt-2 max-w-md">Çok yakında! Harita üzerinden polygon veya çember çizerek yasak/güvenli bölgeler oluşturabileceksiniz.</p>
                </div>
            </div>

            <!-- SEKM: 4. ÇALIŞMA SAATLERİ -->
            <div id="tab-content-schedules" class="hidden space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-800">Mesai Saatleri ve İhlal Alarmları</h3>
                    <p class="text-sm font-bold text-slate-500 mt-2 max-w-md">Çok yakında! Araçların mesai saatleri dışında hareket etmesi durumunda anında bildirim alabileceksiniz.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Modallar -->
    
    <!-- 0. Yönetici Şifresi Modal (Silme Koruması) -->
    <div id="adminDeleteModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAdminDeleteModal()"></div>
        <div class="relative bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="adminDeleteModalContent">
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-rose-50 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-2">Güvenlik Onayı</h3>
                <p class="text-xs font-bold text-slate-500 mb-6">Cihaz eşleştirmesini silmek üzeresiniz. İşleme devam etmek için <b class="text-rose-500">Yönetici Şifrenizi</b> girin.</p>
                
                <input type="hidden" id="deleteVehicleId">
                <input type="password" id="adminDeletePassword" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-black text-sm focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 transition outline-none mb-4 text-center" placeholder="Şifrenizi Girin">
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeAdminDeleteModal()" class="flex-1 py-4 rounded-2xl bg-slate-100 text-slate-600 font-black text-sm hover:bg-slate-200 transition-all">İptal</button>
                    <button type="button" onclick="confirmAdminDelete()" class="flex-1 py-4 rounded-2xl bg-rose-600 text-white font-black text-sm hover:bg-rose-700 transition-all shadow-xl shadow-rose-600/30">Sil</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Gelişmiş Sihirbaz Modal (IMEI, Araç, Şoför) -->
    <div id="wizardModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeWizardModal()"></div>
        <div class="relative bg-white rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="wizardModalContent">
            
            <!-- Başlık -->
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 class="text-xl font-black text-slate-800" id="wizardTitle">Yeni Cihaz Tanımla</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1">Cihaz, Araç ve Şoför Eşleştirme Sihirbazı</p>
                </div>
                <button type="button" onclick="closeWizardModal()" class="h-8 w-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:bg-rose-50 hover:text-rose-600 transition flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Adım Göstergeleri -->
            <div class="px-8 pt-6 flex justify-between relative">
                <div class="absolute top-1/2 left-8 right-8 h-1 bg-slate-100 -translate-y-1/2 z-0 rounded-full"></div>
                <div class="absolute top-1/2 left-8 h-1 bg-indigo-500 -translate-y-1/2 z-0 rounded-full transition-all duration-500" id="wizardProgressBar" style="width: 0%;"></div>
                
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-md border-4 border-white" id="step1Indicator">1</div>
                    <span class="text-[10px] font-black text-slate-400">Cihaz</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors" id="step2Indicator">2</div>
                    <span class="text-[10px] font-black text-slate-400">Bağlantı</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors" id="step3Indicator">3</div>
                    <span class="text-[10px] font-black text-slate-400">Araç</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors" id="step4Indicator">4</div>
                    <span class="text-[10px] font-black text-slate-400">Şoför</span>
                </div>
            </div>

            <div class="p-8">
                <!-- Adım 1: IMEI -->
                <div id="wizardStep1" class="space-y-4 transition-all duration-300">
                    <h4 class="text-lg font-black text-slate-800 mb-4">Takip Cihazını Girin</h4>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-2">Cihaz IMEI Numarası</label>
                        <input type="text" id="wizImei" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-mono text-lg font-bold focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition outline-none" placeholder="Örn: 353210110128749">
                    </div>
                    <button type="button" onclick="goToStep2()" class="w-full py-4 mt-4 rounded-2xl bg-indigo-600 text-white font-black text-sm hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-600/30 flex justify-center items-center gap-2">
                        Bağlan ve Sinyal Ara
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>

                <!-- Adım 2: Harita (Canlı Konum) -->
                <div id="wizardStep2" class="hidden space-y-4 transition-all duration-300">
                    <h4 class="text-lg font-black text-slate-800 mb-2">Bağlantı Kuruldu</h4>
                    <p class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-100 inline-block mb-4">Sinyal başarıyla alındı. Aşağıdaki haritada aracın mevcut konumunu görebilirsiniz.</p>
                    
                    <div id="wizMap" class="w-full h-48 bg-slate-200 rounded-2xl border-4 border-white shadow-lg overflow-hidden"></div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="goToStep(1)" class="w-1/3 py-4 rounded-2xl bg-slate-100 text-slate-600 font-black text-sm hover:bg-slate-200 transition-all">Geri Dön</button>
                        <button type="button" onclick="goToStep3()" class="w-2/3 py-4 rounded-2xl bg-indigo-600 text-white font-black text-sm hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-600/30">Evet, Doğru Konum (İlerle)</button>
                    </div>
                </div>

                <!-- Adım 3: Araç Bilgileri -->
                <div id="wizardStep3" class="hidden space-y-4 transition-all duration-300">
                    <h4 class="text-lg font-black text-slate-800 mb-4">Araç Bilgilerini Girin</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Plaka <span class="text-rose-500">*</span></label>
                            <input type="text" id="wizPlate" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold uppercase focus:border-indigo-500 outline-none" placeholder="34ABC123">
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Marka</label>
                            <input type="text" id="wizBrand" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none" placeholder="Renault">
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Model</label>
                            <input type="text" id="wizModel" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none" placeholder="Megane">
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Yıl</label>
                            <input type="number" id="wizYear" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none" placeholder="2023">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Yakıt Türü</label>
                            <select id="wizFuel" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none">
                                <option value="Dizel">Dizel</option>
                                <option value="Benzin">Benzin</option>
                                <option value="LPG">LPG</option>
                                <option value="Elektrik">Elektrik</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="goToStep(2)" class="w-1/3 py-4 rounded-2xl bg-slate-100 text-slate-600 font-black text-sm hover:bg-slate-200 transition-all">Geri Dön</button>
                        <button type="button" onclick="goToStep4()" class="w-2/3 py-4 rounded-2xl bg-indigo-600 text-white font-black text-sm hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-600/30">İlerle (Şoför Bilgileri)</button>
                    </div>
                </div>

                <!-- Adım 4: Şoför Bilgileri -->
                <div id="wizardStep4" class="hidden space-y-4 transition-all duration-300">
                    <h4 class="text-lg font-black text-slate-800 mb-4">Şoför Bilgilerini Girin</h4>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Ad Soyad <span class="text-rose-500">*</span></label>
                            <input type="text" id="wizDriverName" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none" placeholder="Ahmet Yılmaz">
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Telefon <span class="text-rose-500">*</span></label>
                            <input type="text" id="wizDriverPhone" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 font-bold focus:border-indigo-500 outline-none" placeholder="05321234567">
                        </div>
                        <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mt-2">
                            <div class="flex gap-3 items-start">
                                <svg class="w-5 h-5 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-[11px] font-bold text-indigo-800 leading-tight">Sisteme girilen şoför, girilen araca kilitlenecektir. (1 Şoför = 1 Araç Kuralı). Kayıt işlemi sonrası tüm veriler senkronize edilir.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="goToStep(3)" class="w-1/3 py-4 rounded-2xl bg-slate-100 text-slate-600 font-black text-sm hover:bg-slate-200 transition-all">Geri Dön</button>
                        <button type="button" onclick="submitWizard()" id="wizSubmitBtn" class="w-2/3 py-4 rounded-2xl bg-emerald-600 text-white font-black text-sm hover:bg-emerald-700 transition-all shadow-xl shadow-emerald-600/30 flex justify-center items-center gap-2">
                            Kayıtları Tamamla
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- 2. Yeni Alarm Modal -->
    <div id="alarmModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAlarmModal()"></div>
        <div class="relative bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="alarmModalContent">
            <form action="{{ route('vehicle-tracking.alarms.store') }}" method="POST">
                @csrf
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-black text-slate-800">Yeni Alarm Tanımla</h3>
                        </div>
                        <button type="button" onclick="closeAlarmModal()" class="h-8 w-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-rose-100 hover:text-rose-600 transition flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Hedef Araç</label>
                            <select name="vehicle_id" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-bold text-sm focus:border-indigo-500 outline-none">
                                <option value="">Araç Seçiniz...</option>
                                @foreach($vehiclesWithDevice as $v)
                                    <option value="{{ $v->id }}">{{ $v->plate }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Alarm Tipi</label>
                            <select name="alarm_type" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-bold text-sm focus:border-indigo-500 outline-none">
                                <option value="speed">Hız İhlali (Hız Limitini Aşınca)</option>
                                <option value="stop">Bekleme/Duraklama (X dakikadan fazla durunca)</option>
                                <option value="ignition">Kontak Açıldı/Kapandı</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black uppercase text-slate-600 mb-2">Eşik Değeri (Limit)</label>
                            <input type="number" step="0.01" name="threshold_value" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-slate-900 font-bold text-sm focus:border-indigo-500 outline-none" placeholder="Örn: 90 (km/h) veya 15 (dakika)">
                        </div>
                        
                        <div class="pt-4 border-t border-slate-100">
                            <label class="block text-xs font-black uppercase text-slate-600 mb-3">Bildirim Tercihleri</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="notify_panel" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-bold text-slate-700">Web Panel</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="notify_sms" value="1" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-bold text-slate-700">SMS</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="notify_email" value="1" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm font-bold text-slate-700">E-Posta</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full py-4 rounded-2xl bg-indigo-600 text-white font-black text-sm hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-600/30">
                            Alarmı Kaydet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        function switchTab(tabId) {
            // Hide all
            document.querySelectorAll('[id^="tab-content-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-btn-"]').forEach(el => {
                el.classList.remove('text-indigo-600', 'border-indigo-600');
                el.classList.add('text-slate-400', 'border-transparent');
            });

            // Show selected
            document.getElementById('tab-content-' + tabId).classList.remove('hidden');
            document.getElementById('tab-btn-' + tabId).classList.remove('text-slate-400', 'border-transparent');
            document.getElementById('tab-btn-' + tabId).classList.add('text-indigo-600', 'border-indigo-600');
        }

        // WIZARD AND ADMIN MODALS
        let wizMapInstance = null;
        let wizMarker = null;

        function openImeiModal() {
            // "Yeni Cihaz Tanımla" button clicked
            document.getElementById('wizardTitle').textContent = "Yeni Cihaz Tanımla";
            
            document.getElementById('wizImei').value = "";
            document.getElementById('wizPlate').value = "";
            document.getElementById('wizBrand').value = "";
            document.getElementById('wizModel').value = "";
            document.getElementById('wizYear').value = "";
            document.getElementById('wizFuel').value = "Dizel";
            document.getElementById('wizDriverName').value = "";
            document.getElementById('wizDriverPhone').value = "";
            
            goToStep(1);
            
            const modal = document.getElementById('wizardModal');
            const content = document.getElementById('wizardModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function openWizardEditMode(id, plate, imei, brand, model, year, fuel) {
            document.getElementById('wizardTitle').textContent = "Cihazı Düzenle";
            
            document.getElementById('wizImei').value = imei || "";
            document.getElementById('wizPlate').value = plate || "";
            document.getElementById('wizBrand').value = brand || "";
            document.getElementById('wizModel').value = model || "";
            document.getElementById('wizYear').value = year || "";
            if (fuel) document.getElementById('wizFuel').value = fuel;
            document.getElementById('wizDriverName').value = "";
            document.getElementById('wizDriverPhone').value = "";
            
            // Skip directly to Step 3 for editing
            goToStep(3);
            
            const modal = document.getElementById('wizardModal');
            const content = document.getElementById('wizardModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeWizardModal() {
            const content = document.getElementById('wizardModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('wizardModal').classList.add('hidden');
            }, 300);
        }

        function goToStep(stepIndex) {
            // Hide all steps
            for (let i = 1; i <= 4; i++) {
                document.getElementById('wizardStep' + i).classList.add('hidden');
                
                // Reset Indicators
                const ind = document.getElementById('step' + i + 'Indicator');
                if (i < stepIndex) {
                    ind.className = "w-8 h-8 rounded-full bg-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors";
                    ind.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                } else if (i === stepIndex) {
                    ind.className = "w-8 h-8 rounded-full bg-indigo-500 text-white font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors";
                    ind.innerHTML = i;
                } else {
                    ind.className = "w-8 h-8 rounded-full bg-slate-200 text-slate-500 font-black text-xs flex items-center justify-center shadow-md border-4 border-white transition-colors";
                    ind.innerHTML = i;
                }
            }
            
            // Show target step
            document.getElementById('wizardStep' + stepIndex).classList.remove('hidden');
            
            // Update progress bar width
            const percentages = {1: '0%', 2: '33%', 3: '66%', 4: '100%'};
            document.getElementById('wizardProgressBar').style.width = percentages[stepIndex];
        }

        async function goToStep2() {
            const imei = document.getElementById('wizImei').value.trim();
            if(!imei) return alert('Lütfen IMEI numarasını girin.');
            
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Bağlanıyor... <span class="animate-pulse">⏳</span>';
            btn.disabled = true;
            
            try {
                const fd = new FormData();
                fd.append('imei', imei);
                const res = await fetch("{{ route('vehicle-tracking.wizard.check-imei') }}", {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                    body: fd
                });
                
                const data = await res.json();
                
                if (data.success) {
                    goToStep(2);
                    setTimeout(() => {
                        initWizMap(data.lat, data.lng);
                    }, 400); // Wait for transition
                } else {
                    alert(data.message || 'Bir hata oluştu.');
                }
            } catch (err) {
                alert('Bağlantı hatası.');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        function initWizMap(lat, lng) {
            if (!wizMapInstance) {
                wizMapInstance = L.map('wizMap', { zoomControl: false }).setView([lat, lng], 15);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(wizMapInstance);
            }
            wizMapInstance.setView([lat, lng], 15);
            
            if (wizMarker) wizMapInstance.removeLayer(wizMarker);
            wizMarker = L.marker([lat, lng]).addTo(wizMapInstance);
            
            setTimeout(() => { wizMapInstance.invalidateSize(); }, 200);
        }

        function goToStep3() {
            goToStep(3);
        }

        function goToStep4() {
            const plate = document.getElementById('wizPlate').value.trim();
            if(!plate) return alert('Plaka girmek zorunludur.');
            goToStep(4);
        }

        async function submitWizard() {
            const imei = document.getElementById('wizImei').value.trim();
            const plate = document.getElementById('wizPlate').value.trim();
            const driverName = document.getElementById('wizDriverName').value.trim();
            const driverPhone = document.getElementById('wizDriverPhone').value.trim();
            
            if(!driverName || !driverPhone) return alert('Lütfen şoför ad soyad ve telefon bilgilerini giriniz.');
            
            const btn = document.getElementById('wizSubmitBtn');
            btn.innerHTML = 'Kaydediliyor... <span class="animate-pulse">⏳</span>';
            btn.disabled = true;
            
            const fd = new FormData();
            fd.append('imei', imei);
            fd.append('plate', plate);
            fd.append('brand', document.getElementById('wizBrand').value);
            fd.append('model', document.getElementById('wizModel').value);
            fd.append('model_year', document.getElementById('wizYear').value);
            fd.append('fuel_type', document.getElementById('wizFuel').value);
            fd.append('driver_name', driverName);
            fd.append('driver_phone', driverPhone);
            
            try {
                const res = await fetch("{{ route('vehicle-tracking.wizard.store') }}", {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                    body: fd
                });
                const data = await res.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Kayıt başarısız.');
                    btn.innerHTML = 'Kayıtları Tamamla';
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Bağlantı hatası.');
                btn.innerHTML = 'Kayıtları Tamamla';
                btn.disabled = false;
            }
        }

        // ADMIN DELETE MODAL
        function openAdminDeleteModal(vehicleId, plate) {
            document.getElementById('deleteVehicleId').value = vehicleId;
            document.getElementById('adminDeletePassword').value = '';
            
            const modal = document.getElementById('adminDeleteModal');
            const content = document.getElementById('adminDeleteModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
                document.getElementById('adminDeletePassword').focus();
            }, 10);
        }

        function closeAdminDeleteModal() {
            const content = document.getElementById('adminDeleteModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('adminDeleteModal').classList.add('hidden');
            }, 300);
        }

        async function confirmAdminDelete() {
            const vid = document.getElementById('deleteVehicleId').value;
            const pwd = document.getElementById('adminDeletePassword').value;
            
            if(!pwd) return alert('Lütfen yönetici şifrenizi girin.');
            
            const fd = new FormData();
            fd.append('vehicle_id', vid);
            fd.append('password', pwd);
            
            try {
                const res = await fetch("{{ route('vehicle-tracking.wizard.remove-device') }}", {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                    body: fd
                });
                const data = await res.json();
                
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Bağlantı hatası');
            }
        }

        async function fixFakeLocations() {
            if(!confirm('Test amaçlı oluşturulan sahte İstanbul konumları temizlenecektir. Emin misiniz?')) return;
            
            try {
                const res = await fetch("{{ route('fix-konum') }}", {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Hatalı (İstanbul) konumlar temizlendi! Toplam: ' + data.count);
                    window.location.reload();
                } else {
                    alert('Bir hata oluştu.');
                }
            } catch (e) {
                alert('Bağlantı hatası: ' + e);
            }
        }

        function openAlarmModal() {
            const modal = document.getElementById('alarmModal');
            const content = document.getElementById('alarmModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeAlarmModal() {
            const content = document.getElementById('alarmModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('alarmModal').classList.add('hidden');
            }, 300);
        }
    </script>
</body>
</html>
