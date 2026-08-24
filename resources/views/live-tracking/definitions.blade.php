<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filomerkez - Tanımlamalar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                        <h3 class="text-lg font-black text-slate-800">Cihaz Atamaları</h3>
                        <p class="text-xs font-bold text-slate-400">Toplam {{ $vehicles->count() }} araç</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($vehicles as $v)
                        <div class="p-4 rounded-2xl border {{ $v->device_imei ? 'border-indigo-100 bg-indigo-50/30' : 'border-slate-100 bg-slate-50' }} flex flex-col justify-between hover:shadow-md transition-shadow relative overflow-hidden group">
                            
                            <!-- Durum Göstergesi -->
                            @if($v->device_status === 'online')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    ONLINE
                                </div>
                            @elseif($v->device_status === 'offline')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-rose-100 text-rose-700 text-[10px] font-black">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    OFFLINE
                                </div>
                            @elseif($v->device_status === 'never')
                                <div class="absolute top-4 right-4 flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-200 text-slate-600 text-[10px] font-black">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    SİNYAL YOK
                                </div>
                            @endif

                            <div>
                                <h4 class="text-base font-black text-slate-800">{{ $v->plate }}</h4>
                                <p class="text-xs font-bold text-slate-500 mt-1">{{ $v->brand }} {{ $v->model }}</p>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                                <div>
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Cihaz IMEI</span>
                                    @if($v->device_imei)
                                        <span class="text-sm font-black text-indigo-600 font-mono">{{ $v->device_imei }}</span>
                                    @else
                                        <span class="text-xs font-bold text-rose-500">Cihaz Yok</span>
                                    @endif
                                </div>
                                <button onclick="openImeiModal({{ $v->id }}, '{{ $v->plate }}', '{{ $v->device_imei }}')" class="h-8 w-8 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-indigo-600 hover:border-indigo-200 flex items-center justify-center transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </button>
                            </div>
                        </div>
                        @endforeach
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
    
    <!-- 1. IMEI Modal -->
    <div id="imeiModal" class="fixed inset-0 z-[2000] hidden flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeImeiModal()"></div>
        <div class="relative bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden transform scale-95 opacity-0 transition-all duration-300" id="imeiModalContent">
            <form action="{{ route('vehicle-tracking.assign-imei') }}" method="POST">
                @csrf
                <input type="hidden" name="vehicle_id" id="modalVehicleId">
                
                <div class="p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-black text-slate-800" id="modalVehiclePlate">34 ABC 123</h3>
                            <p class="text-xs font-bold text-slate-400 mt-1">Cihaz Eşleştirme</p>
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
                                @foreach($vehicles as $v)
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

        // Modals
        function openImeiModal(id, plate, imei) {
            document.getElementById('modalVehicleId').value = id;
            document.getElementById('modalVehiclePlate').textContent = plate;
            document.getElementById('modalDeviceImei').value = imei || '';
            
            const modal = document.getElementById('imeiModal');
            const content = document.getElementById('imeiModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeImeiModal() {
            const content = document.getElementById('imeiModalContent');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                document.getElementById('imeiModal').classList.add('hidden');
            }, 300);
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
