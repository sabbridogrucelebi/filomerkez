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
    <div class="flex-1 flex flex-col h-full bg-slate-50 relative">
        
        <!-- Üst Bar -->
        <div class="h-20 bg-white border-b border-slate-200 px-8 flex items-center justify-between shrink-0 shadow-sm z-10 relative">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Gelişmiş Raporlar Paneli</h2>
                <p class="text-xs font-bold text-slate-400 mt-0.5">Araçların geçmiş kullanım istatistikleri ve analizleri</p>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-xl border-2 border-slate-200 text-slate-600 font-bold text-sm hover:bg-slate-50 hover:border-slate-300 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"></path></svg>
                    Panele Dön
                </a>
            </div>
        </div>

        <!-- İçerik Alanı -->
        <div class="flex-1 p-8 overflow-y-auto custom-scrollbar relative flex items-center justify-center">
            
            <div class="text-center max-w-md mx-auto">
                <div class="w-24 h-24 bg-amber-100 text-amber-500 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl shadow-amber-500/20 border border-amber-200">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <h3 class="text-2xl font-black text-slate-800 mb-3">Raporlar Paneli Hazır</h3>
                <p class="text-sm font-bold text-slate-500">Altyapı hazırlandı, istediğiniz özellikleri anlatmanızı bekliyorum...</p>
            </div>

        </div>
    </div>
</body>
</html>
