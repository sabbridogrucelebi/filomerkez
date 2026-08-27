<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gelişmiş Raporlar - FiloMerkez</title>
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
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-50 font-sans antialiased text-slate-800 flex">

    <!-- Mavi Premium Full-Height Sidebar -->
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
            
            <a href="{{ route('vehicle-tracking.index') }}" class="sidebar-item" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                <div class="item-text">
                    <span>Canlı Harita</span>
                    <span>Tüm filoyu anlık izle</span>
                </div>
            </a>

            <a href="{{ route('vehicle-tracking.definitions') }}" class="sidebar-item" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <div class="item-text">
                    <span>Cihaz Tanımlamaları</span>
                    <span>Ayarlar ve konfigürasyon</span>
                </div>
            </a>

            <a href="{{ route('vehicle-tracking.reports') }}" class="sidebar-item active" style="position:relative;">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                <div class="item-text">
                    <span>Gelişmiş Raporlar</span>
                    <span>Geçmiş veri analizleri</span>
                </div>
            </a>

        </div>
    </div>

    <!-- Ana İçerik Alanı -->
    <div class="flex-1 flex flex-col h-full bg-slate-50 relative" x-data="reportsDashboard()">
        
        <!-- Üst Bar -->
        <div class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0 shadow-sm z-10 relative">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">AI Analitik Paneli <span class="bg-indigo-100 text-indigo-700 text-[10px] px-2 py-0.5 rounded-full ml-2 align-top">BETA</span></h2>
                <p class="text-xs font-bold text-slate-400 mt-0.5">FiloMerkez Yapay Zeka Destekli Performans ve Maliyet Analizleri</p>
            </div>
            
            <div class="flex items-center gap-4">
                <select x-model="dateRange" @change="fetchData()" class="bg-slate-50 border-2 border-slate-200 text-slate-700 text-sm font-bold rounded-xl px-4 py-2 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 transition-all cursor-pointer">
                    <option value="today">Bugün</option>
                    <option value="yesterday">Dün</option>
                    <option value="7days">Son 7 Gün</option>
                    <option value="30days">Son 30 Gün</option>
                </select>
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50 hover:border-slate-300 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
                    Panele Dön
                </a>
            </div>
        </div>

        <!-- Yükleniyor Ekranı -->
        <div x-show="loading" class="absolute inset-0 bg-slate-50/80 backdrop-blur-sm z-50 flex flex-col items-center justify-center" style="display: none;">
            <div class="w-16 h-16 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
            <p class="text-indigo-900 font-bold tracking-widest text-sm animate-pulse">YAPAY ZEKA VERİLERİ İŞLİYOR...</p>
        </div>

        <!-- İçerik Alanı -->
        <div class="flex-1 p-8 overflow-y-auto custom-scrollbar relative">
            
            <!-- Üst Kartlar (Overview) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Eco Score -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-50 rounded-full"></div>
                    <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center shrink-0 z-10">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div class="z-10">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Filo Eco-Score</p>
                        <h4 class="text-3xl font-black text-slate-800" x-text="overview.avg_eco_score + ' / 100'"></h4>
                    </div>
                </div>

                <!-- TL İsrafı -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-5 relative overflow-hidden group hover:border-rose-200 transition-all">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-rose-50 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                    <div class="w-14 h-14 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center shrink-0 z-10">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="z-10">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Rölanti Zararı</p>
                        <h4 class="text-3xl font-black text-rose-600" x-text="'₺' + overview.total_loss_tl.toLocaleString('tr-TR')"></h4>
                    </div>
                </div>

                <!-- Aktiflik -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-50 rounded-full"></div>
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center shrink-0 z-10">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div class="z-10">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Kapasite Kullanımı</p>
                        <h4 class="text-3xl font-black text-slate-800" x-text="'%' + overview.avg_capacity"></h4>
                    </div>
                </div>
                
                <!-- Mesafe -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-slate-100 flex items-center gap-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-50 rounded-full"></div>
                    <div class="w-14 h-14 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center shrink-0 z-10">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    </div>
                    <div class="z-10">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Toplam Mesafe</p>
                        <h4 class="text-3xl font-black text-slate-800" x-text="overview.total_distance + ' KM'"></h4>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Şoför Karnesi (Leaderboard) -->
                <div class="lg:col-span-2 bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.04)] border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-black text-slate-800">🏆 Şoför Sürüş Karnesi</h3>
                            <p class="text-xs font-bold text-slate-400 mt-1">İhlaller ve performans puanlarına göre sıralama</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider">Şoför / Araç</th>
                                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">İhlaller</th>
                                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-center">Yakıt İsrafı</th>
                                    <th class="py-4 px-6 text-xs font-black text-slate-400 uppercase tracking-wider text-right">Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in leaderboard" :key="item.vehicle_id">
                                    <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                                    <span x-text="item.driver ? item.driver.charAt(0) : '?'"></span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-black text-slate-800" x-text="item.driver || 'Bilinmeyen Şoför'"></p>
                                                    <p class="text-xs font-bold text-slate-400" x-text="item.plate"></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <span x-show="item.violations > 0" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 text-xs font-bold">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                                <span x-text="item.violations + ' İhlal'"></span>
                                            </span>
                                            <span x-show="item.violations === 0" class="text-xs font-bold text-slate-300">-</span>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <span class="text-sm font-black text-rose-500" x-text="'₺' + item.total_loss_tl"></span>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <span class="text-lg font-black" 
                                                  :class="{'text-emerald-500': item.avg_score >= 90, 'text-amber-500': item.avg_score < 90 && item.avg_score >= 70, 'text-rose-500': item.avg_score < 70}"
                                                  x-text="item.avg_score"></span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="leaderboard.length === 0">
                                    <td colspan="4" class="py-8 text-center text-slate-400 font-bold text-sm">Seçili tarihte veri bulunamadı.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sağ Taraf (Kestirimci Bakım & Grafikler) -->
                <div class="flex flex-col gap-8">
                    
                    <!-- Kestirimci Bakım -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-rose-500 rounded-full mix-blend-screen filter blur-[50px] opacity-20"></div>
                        <h3 class="text-lg font-black text-white mb-1">🛠️ Kestirimci Bakım</h3>
                        <p class="text-xs font-bold text-slate-400 mb-6">Yapay zeka aşınma ve arıza tahminleri</p>
                        
                        <div class="space-y-4">
                            <template x-for="alert in maintenanceAlerts" :key="alert.plate">
                                <div class="bg-slate-800/50 border border-slate-700 p-4 rounded-2xl">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-bold text-white" x-text="alert.plate"></span>
                                        <span class="text-xs font-black text-rose-400">Riskli Bölge</span>
                                    </div>
                                    <p class="text-xs font-bold text-slate-400 mb-2">Sert frenlerden kaynaklı tahmini balata aşınması</p>
                                    <div class="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
                                        <div class="bg-gradient-to-r from-amber-400 to-rose-500 h-full rounded-full" :style="'width: ' + alert.brake_wear_percent + '%'"></div>
                                    </div>
                                    <p class="text-right text-[10px] font-black text-slate-300 mt-1" x-text="'%' + alert.brake_wear_percent"></p>
                                </div>
                            </template>
                            <div x-show="maintenanceAlerts.length === 0" class="text-center py-4">
                                <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <p class="text-xs font-bold text-emerald-400">Tüm filonun sağlık durumu mükemmel.</p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
    
    <!-- Alpine.js & Chart.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('reportsDashboard', () => ({
                dateRange: '7days',
                loading: false,
                overview: {
                    avg_eco_score: 0,
                    total_loss_tl: 0,
                    total_distance: 0,
                    total_idle_hours: 0,
                    avg_capacity: 0
                },
                leaderboard: [],
                maintenanceAlerts: [],
                
                init() {
                    this.fetchData();
                },
                
                async fetchData() {
                    this.loading = true;
                    
                    // Tarih hesaplama
                    const today = new Date();
                    let start = new Date();
                    if(this.dateRange === 'today') start = today;
                    else if(this.dateRange === 'yesterday') { start.setDate(today.getDate()-1); today.setDate(today.getDate()-1); }
                    else if(this.dateRange === '7days') start.setDate(today.getDate()-7);
                    else if(this.dateRange === '30days') start.setDate(today.getDate()-30);
                    
                    const startStr = start.toISOString().split('T')[0];
                    const endStr = today.toISOString().split('T')[0];
                    
                    try {
                        const res = await fetch(`{{ route('vehicle-tracking.reports.data') }}?start_date=${startStr}&end_date=${endStr}`);
                        const data = await res.json();
                        
                        this.overview = data.overview;
                        this.leaderboard = data.leaderboard;
                        this.maintenanceAlerts = data.maintenanceAlerts;
                        
                    } catch (err) {
                        console.error(err);
                        alert("Veriler yüklenirken hata oluştu.");
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
