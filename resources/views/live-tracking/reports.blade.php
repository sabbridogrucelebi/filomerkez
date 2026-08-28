<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akıllı Rapor Asistanı - FiloMerkez</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 16px;
            margin-bottom: 8px;
            border-radius: 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }
        .sidebar-item:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-color: rgba(255,255,255,0.1);
        }
        .sidebar-item.active {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
            color: white;
            border-color: rgba(255,255,255,0.2);
            box-shadow: inset 0 1px 1px rgba(255,255,255,0.15), 0 4px 15px rgba(0,0,0,0.2);
        }
        .sidebar-item svg {
            width: 24px;
            height: 24px;
            margin-right: 16px;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .sidebar-item.active svg {
            color: #34d399;
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

        /* Arvento Style Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Premium 3D Card Hover Effects */
        .report-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        .report-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px -5px rgba(0,0,0,0.08), 0 10px 15px -5px rgba(0,0,0,0.04);
            border-color: #e2e8f0;
        }
        .report-card:hover .report-icon-wrapper {
            transform: translateZ(20px) scale(1.1);
        }
        .report-card:hover .open-btn {
            background-color: #10b981;
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .report-icon-wrapper {
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Assistant Modal Animation */
        .assistant-slide-up-enter { transform: translateY(100%); opacity: 0; }
        .assistant-slide-up-enter-active { transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1); transform: translateY(0); opacity: 1; }
        .assistant-slide-up-leave { transform: translateY(0); opacity: 1; }
        .assistant-slide-up-leave-active { transition: all 0.3s ease-in; transform: translateY(100%); opacity: 0; }
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-50 font-sans antialiased text-slate-800 flex" x-data="reportCatalog()">

    <!-- Mavi Premium Full-Height Sidebar (Aynı kalacak) -->
    <div id="blueSidebar" class="flex flex-col overflow-hidden shrink-0 w-72 bg-gradient-to-b from-indigo-900 via-slate-900 to-black shadow-2xl relative z-20">
        
        <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3N2Zz4=')] opacity-30"></div>
        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500 rounded-full mix-blend-screen filter blur-[80px] opacity-20 translate-x-1/2 -translate-y-1/2"></div>
        
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
        <div style="flex:1; padding:28px 16px; display:flex; flex-direction:column; gap:8px; overflow-y:auto; z-10 relative">
            <a href="{{ route('vehicle-tracking.index') }}" class="sidebar-item">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <div class="item-text">
                    <span>Canlı Harita</span>
                    <span>Tüm filoyu anlık izle</span>
                </div>
            </a>
            <a href="{{ route('vehicle-tracking.definitions') }}" class="sidebar-item">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <div class="item-text">
                    <span>Cihaz Tanımlamaları</span>
                    <span>Ayarlar ve konfigürasyon</span>
                </div>
            </a>
            <a href="{{ route('vehicle-tracking.reports') }}" class="sidebar-item active">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <div class="item-text">
                    <span>Akıllı Raporlar</span>
                    <span>Geçmiş veri analizleri</span>
                </div>
            </a>
        </div>
    </div>

    <!-- MAIN CATALOG VIEW -->
    <div x-show="view === 'catalog'" class="flex-1 flex flex-col h-full bg-slate-50 relative" style="display: none;">
        
        <!-- Üst Bar (Arvento Tarzı Temiz) -->
        <div class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0 z-10 relative">
            <div class="flex items-center gap-6">
                <h2 class="text-xl font-black text-slate-800 tracking-tight uppercase">Akıllı Rapor Asistanı</h2>
                
                <!-- Arama Çubuğu -->
                <div class="relative w-96 hidden md:block">
                    <input type="text" placeholder="Ne tür bir rapor arıyorsunuz? Örn: araçlarımın dünkü çalışma saatleri..." 
                           class="w-full bg-slate-50 border border-slate-200 rounded-full py-2.5 pl-12 pr-4 text-sm font-semibold text-slate-600 focus:outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 transition-all placeholder:text-slate-400">
                    <svg class="w-5 h-5 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>
            
            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-600 font-bold text-sm hover:bg-slate-200 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
                Panele Dön
            </a>
        </div>

        <!-- Alt Sekmeler -->
        <div class="bg-white border-b border-slate-200 px-8 flex gap-8">
            <button class="py-4 border-b-2 border-indigo-600 text-indigo-700 font-black tracking-wide text-sm">KATALOG</button>
            <button class="py-4 border-b-2 border-transparent text-slate-400 font-bold tracking-wide text-sm hover:text-slate-600 transition-colors">SENSÖRLERİNİZE ÖZEL</button>
            <button class="py-4 border-b-2 border-transparent text-slate-400 font-bold tracking-wide text-sm hover:text-slate-600 transition-colors">SON KULLANDIĞINIZ RAPORLAR</button>
        </div>

        <!-- Rapor Kartları (Grid) -->
        <div class="flex-1 p-8 overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 max-w-7xl mx-auto">
                
                <!-- 1. Araç Çalışma Raporu -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col report-card">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-100 to-teal-50 flex items-center justify-center shrink-0 report-icon-wrapper shadow-inner border border-emerald-100">
                            <svg class="w-8 h-8 text-emerald-600 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Araç Çalışma Raporu</h3>
                            <p class="text-xs font-semibold text-slate-500 mt-1 leading-relaxed">
                                Araçların günlük çalışma durumunu en kapsamlı şekilde özetleyen rapordur. GPS konum verisi ve kontak durumlarına göre mesai saatlerini dökümler.
                            </p>
                        </div>
                    </div>
                    <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button class="px-4 py-2 text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">Önizle</button>
                        <button @click="openAssistant('working_report')" class="open-btn px-6 py-2 bg-emerald-500 text-white text-sm font-black rounded-xl hover:bg-emerald-600 transition-all shadow-sm">
                            Raporu Aç
                        </button>
                    </div>
                </div>

                <!-- 2. Mesafe Bilgisi Raporu -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col report-card opacity-70">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-100 to-indigo-50 flex items-center justify-center shrink-0 report-icon-wrapper shadow-inner border border-blue-100">
                            <svg class="w-8 h-8 text-blue-600 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Mesafe Bilgisi Raporu</h3>
                            <p class="text-xs font-semibold text-slate-500 mt-1 leading-relaxed">
                                GPS konum verisinden hesaplanan kilometre sayacı değerlerini kullanarak seçilen dönem boyunca aracın yaptığı toplam yolu gösterir.
                            </p>
                        </div>
                    </div>
                    <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <span class="text-xs font-bold text-amber-500">Çok Yakında</span>
                        <button disabled class="open-btn px-6 py-2 bg-slate-100 text-slate-400 text-sm font-black rounded-xl cursor-not-allowed">
                            Raporu Aç
                        </button>
                    </div>
                </div>

                <!-- 3. Kontak Alarmı Raporu -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 flex flex-col report-card opacity-70">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-100 to-orange-50 flex items-center justify-center shrink-0 report-icon-wrapper shadow-inner border border-rose-100">
                            <svg class="w-8 h-8 text-rose-600 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800">Kontak Alarmı</h3>
                            <p class="text-xs font-semibold text-slate-500 mt-1 leading-relaxed">
                                Araçların kontak açma ve kontak kapama olaylarını anlık olarak kaydeder. Her olay için hız değeri ve konum bilgisi sunulur.
                            </p>
                        </div>
                    </div>
                    <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <span class="text-xs font-bold text-amber-500">Çok Yakında</span>
                        <button disabled class="open-btn px-6 py-2 bg-slate-100 text-slate-400 text-sm font-black rounded-xl cursor-not-allowed">
                            Raporu Aç
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ASSISTANT VIEW (OVERLAY MODAL) -->
    <div x-show="view === 'assistant'" x-transition:enter="assistant-slide-up-enter-active" x-transition:enter-start="assistant-slide-up-enter" x-transition:enter-end="assistant-slide-up-leave" x-transition:leave="assistant-slide-up-leave-active" x-transition:leave-start="assistant-slide-up-leave" x-transition:leave-end="assistant-slide-up-enter" class="fixed inset-0 z-50 bg-slate-50 flex flex-col" style="display: none;">
        
        <!-- Asistan Üst Bar -->
        <div class="h-16 bg-indigo-900 flex items-center px-6 shrink-0 shadow-lg relative z-20 text-white">
            <button @click="closeAssistant()" class="p-2 hover:bg-white/10 rounded-lg transition-colors mr-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <div class="flex items-center gap-3 border-l border-white/20 pl-4">
                <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                </div>
                <h2 class="text-lg font-black tracking-wide" x-text="assistantTitle"></h2>
            </div>
            
            <div class="ml-auto flex items-center gap-4">
                <button @click="generateReport()" class="px-6 py-2 bg-emerald-500 hover:bg-emerald-400 text-white font-black text-sm rounded-lg transition-all shadow-[0_0_15px_rgba(16,185,129,0.5)] flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Raporu Sorgula
                </button>
            </div>
        </div>

        <!-- Asistan İçerik Alanı -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Sol Panel (Parametreler) -->
            <div class="w-80 bg-white border-r border-slate-200 p-6 overflow-y-auto custom-scrollbar flex flex-col gap-6 shrink-0 shadow-lg relative z-10">
                
                <!-- Araç Seçimi -->
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Araç Seçimi</label>
                    <select x-model="selectedVehicle" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all">
                        <option value="all">Tüm Filo</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->plate }} ({{ $v->driver ?? 'Şoför Yok' }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tarih Seçimi -->
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-wider mb-2">Tarih Aralığı</label>
                    <select x-model="selectedDateRange" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all mb-3">
                        <option value="today">Bugün</option>
                        <option value="yesterday">Dün</option>
                        <option value="this_week">Bu Hafta</option>
                        <option value="last_7_days">Son 7 Gün</option>
                    </select>
                </div>

                <div class="mt-auto p-4 bg-indigo-50 rounded-2xl border border-indigo-100">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h4 class="text-sm font-black text-indigo-900">İpucu</h4>
                    </div>
                    <p class="text-xs font-semibold text-indigo-700 leading-relaxed">
                        Daha hızlı sonuç almak için geniş tarih aralıkları yerine son 7 günü tercih edebilirsiniz. Raporları sağ üstten Excel formatında indirebilirsiniz.
                    </p>
                </div>
            </div>

            <!-- Sağ Panel (Rapor Sonucu) -->
            <div class="flex-1 bg-slate-50 relative overflow-hidden flex flex-col">
                
                <!-- Yükleniyor Göstergesi -->
                <div x-show="loading" class="absolute inset-0 bg-white/50 backdrop-blur-sm z-50 flex flex-col items-center justify-center">
                    <div class="w-12 h-12 border-4 border-slate-200 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-indigo-900 font-bold tracking-widest text-xs uppercase animate-pulse">Sistem Verileri Derliyor...</p>
                </div>

                <!-- Sonuç Tablosu -->
                <div x-show="!loading && reportData.length > 0" class="flex-1 p-8 overflow-y-auto custom-scrollbar" style="display: none;">
                    
                    <!-- Özet Kartları (Eğer data geldiyse en üste mini özet koyalım) -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Toplam Mesafe</p>
                            <h4 class="text-2xl font-black text-slate-800" x-text="totalSummary.distance + ' KM'"></h4>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Toplam Rölanti</p>
                            <h4 class="text-2xl font-black text-rose-600" x-text="totalSummary.idle + ' Dk'"></h4>
                        </div>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                            <p class="text-xs font-bold text-slate-400 uppercase mb-1">Rölanti Zararı</p>
                            <h4 class="text-2xl font-black text-slate-800" x-text="'₺' + totalSummary.loss"></h4>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-100/50 border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-6 font-black text-slate-500 uppercase tracking-wider text-xs">Tarih</th>
                                    <th class="py-3 px-6 font-black text-slate-500 uppercase tracking-wider text-xs">Plaka</th>
                                    <th class="py-3 px-6 font-black text-slate-500 uppercase tracking-wider text-xs text-center">Çalışma (Dk)</th>
                                    <th class="py-3 px-6 font-black text-slate-500 uppercase tracking-wider text-xs text-center">Rölanti (Dk)</th>
                                    <th class="py-3 px-6 font-black text-slate-500 uppercase tracking-wider text-xs text-right">Mesafe (KM)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in reportData" :key="row.id">
                                    <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3 px-6 font-bold text-slate-700" x-text="row.date"></td>
                                        <td class="py-3 px-6">
                                            <div class="font-black text-slate-800" x-text="row.plate"></div>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <span class="inline-block px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 font-bold text-xs" x-text="row.active_mins"></span>
                                        </td>
                                        <td class="py-3 px-6 text-center">
                                            <span class="inline-block px-2.5 py-1 rounded bg-rose-50 text-rose-700 font-bold text-xs" x-text="row.idle_mins"></span>
                                        </td>
                                        <td class="py-3 px-6 text-right font-black text-slate-700" x-text="row.distance"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Boş Durum (İlk açılış) -->
                <div x-show="!loading && !hasSearched" class="flex-1 flex flex-col items-center justify-center p-8 text-center" style="display: none;">
                    <div class="w-24 h-24 mb-6 relative">
                        <div class="absolute inset-0 bg-slate-200 rounded-full animate-ping opacity-20"></div>
                        <div class="absolute inset-2 bg-slate-100 rounded-full flex items-center justify-center border border-slate-200">
                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                    </div>
                    <h3 class="text-xl font-black text-slate-700 mb-2">Parametreleri Belirleyin</h3>
                    <p class="text-sm font-semibold text-slate-500 max-w-sm">
                        Raporu oluşturmak için sol taraftaki panelden araç ve tarih aralığı seçip "Raporu Sorgula" butonuna basın.
                    </p>
                </div>

                <!-- Sonuç Bulunamadı -->
                <div x-show="!loading && hasSearched && reportData.length === 0" class="flex-1 flex flex-col items-center justify-center p-8 text-center" style="display: none;">
                    <div class="w-24 h-24 mb-6 bg-rose-50 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-black text-slate-700 mb-2">Sonuç Bulunamadı</h3>
                    <p class="text-sm font-semibold text-slate-500 max-w-sm">
                        Seçtiğiniz tarih aralığında bu araca ait herhangi bir rapor verisi oluşturulmamış. Lütfen "Dün" veya "Son 7 Gün" seçeneğini deneyin.
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Alpine.js (Core Logic) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('reportCatalog', () => ({
                view: 'catalog', // 'catalog' veya 'assistant'
                assistantType: null,
                assistantTitle: '',
                
                // Form State
                selectedVehicle: 'all',
                selectedDateRange: 'today',
                
                // Data State
                loading: false,
                hasSearched: false,
                reportData: [],
                totalSummary: { distance: 0, idle: 0, loss: 0 },

                init() {
                    // Sayfa yüklendiğinde katalog modundayız
                    this.view = 'catalog';
                },

                openAssistant(type) {
                    this.assistantType = type;
                    if(type === 'working_report') this.assistantTitle = 'Araç Çalışma Raporu';
                    else this.assistantTitle = 'Rapor Asistanı';
                    
                    // Verileri temizle
                    this.hasSearched = false;
                    this.reportData = [];
                    this.totalSummary = { distance: 0, idle: 0, loss: 0 };
                    
                    // Modalı tam ekran aç
                    this.view = 'assistant';
                },

                closeAssistant() {
                    this.view = 'catalog';
                },

                async generateReport() {
                    this.loading = true;
                    this.hasSearched = true;
                    
                    // Parametreleri hazırla
                    const today = new Date();
                    let start = new Date();
                    if(this.selectedDateRange === 'today') start = today;
                    else if(this.selectedDateRange === 'yesterday') { start.setDate(today.getDate()-1); today.setDate(today.getDate()-1); }
                    else if(this.selectedDateRange === 'this_week') {
                        const day = today.getDay();
                        const diff = today.getDate() - day + (day == 0 ? -6:1); // Monday
                        start = new Date(today.setDate(diff));
                    }
                    else if(this.selectedDateRange === 'last_7_days') start.setDate(today.getDate()-7);
                    
                    // Gerçek production API çağrısı yapılana kadar simüle edelim, API tarafı hazır.
                    try {
                        const res = await fetch(`{{ route('vehicle-tracking.reports.working') }}?start_date=${start.toISOString().split('T')[0]}&end_date=${new Date().toISOString().split('T')[0]}&vehicle_id=${this.selectedVehicle}`);
                        
                        if(res.ok) {
                            const data = await res.json();
                            this.reportData = data.rows;
                            
                            // Özetleri hesapla
                            this.totalSummary.distance = this.reportData.reduce((acc, val) => acc + val.distance, 0);
                            this.totalSummary.idle = this.reportData.reduce((acc, val) => acc + val.idle_mins, 0);
                            this.totalSummary.loss = this.reportData.reduce((acc, val) => acc + val.idle_loss_tl, 0);
                        } else {
                            throw new Error("API Hatası");
                        }
                    } catch (error) {
                        console.error(error);
                        alert("Rapor getirilirken bir hata oluştu.");
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
