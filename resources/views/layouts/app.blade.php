<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? '') ? $title . ' • ' : '' }}FiloMERKEZ</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200..800&display=swap');

        :root {
            --sidebar-bg: #0f172a; /* Biraz daha derin lacivert */
            --sidebar-hover: rgba(255, 255, 255, 0.08);
            --accent-primary: #818cf8; /* Daha parlak indigo */
            --accent-secondary: #c084fc; /* Daha parlak mor */
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
        }

        .sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
        }

        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }

        .glass-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .mesh-gradient {
            background-color: var(--sidebar-bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.25) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(168, 85, 247, 0.15) 0px, transparent 50%),
                radial-gradient(at 50% 100%, rgba(79, 70, 229, 0.1) 0px, transparent 50%);
        }

        .nav-item-active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.15) 0%, transparent 100%);
            border-left: 4px solid var(--accent-primary);
            box-shadow: inset 4px 0 10px -2px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased overflow-x-hidden">

@php
    $user = auth()->user();
    $company = $user?->company;

    $expiredVehicleDocuments = collect();
    $driverDocumentAlerts = collect();

    if ($user && $company) {
        $expiredVehicleDocuments = \App\Models\Document::query()
            ->where('documentable_type', \App\Models\Fleet\Vehicle::class)
            ->whereNull('archived_at') // Arşivlenmiş belgeleri tamamen yoksay
            ->whereNotNull('end_date')
            ->with('documentable')
            ->orderByDesc('end_date') // En yeni tarihli olanı en üste al
            ->get()
            ->filter(fn ($doc) => $doc->documentable && $doc->documentable->is_active)
            ->unique(fn ($doc) => $doc->documentable_id . '_' . $doc->document_type) // Her araç için her belge tipinden sadece EN GÜNCEL olanı tut
            ->filter(fn ($doc) => $doc->end_date->startOfDay()->lt(now()->startOfDay())) // Kalan en güncel belgelerden günü geçmiş olanları filtrele
            ->sortBy('end_date') // Yakın tarihte bitenleri önce göster
            ->values();

        $driverDocumentAlerts = collect(\App\Http\Controllers\DriverController::getDriverDocumentAlertsForLayout());

        $criticalMaintenances = collect();
        $vehiclesForMaint = \App\Models\Fleet\Vehicle::with(['maintenanceSetting', 'maintenances'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();
            
        foreach ($vehiclesForMaint as $v) {
            $mStatus = $v->maintenance_status;
            if ($mStatus['has_oil_setting'] && $mStatus['oil_remaining'] !== null && $mStatus['oil_remaining'] <= 200) {
                $criticalMaintenances->push([
                    'vehicle_id' => $v->id,
                    'plate' => $v->plate,
                    'type' => 'Yağ Değişimi',
                    'remaining' => $mStatus['oil_remaining'],
                    'critical' => $mStatus['oil_remaining'] < 0
                ]);
            }
            if ($mStatus['has_lube_setting'] && $mStatus['lube_remaining'] !== null && $mStatus['lube_remaining'] <= 200) {
                $criticalMaintenances->push([
                    'vehicle_id' => $v->id,
                    'plate' => $v->plate,
                    'type' => 'Alt Yağlama',
                    'remaining' => $mStatus['lube_remaining'],
                    'critical' => $mStatus['lube_remaining'] < 0
                ]);
            }
        }
    }
@endphp

<div class="min-h-screen">
    <div class="flex">

        <!-- SIDEBAR -->
        <aside class="fixed left-0 top-0 z-40 h-screen w-72 mesh-gradient text-slate-400 shadow-[20px_0_40px_rgba(0,0,0,0.2)] transition-all duration-500 overflow-hidden border-r border-white/5">
            <div class="flex h-full flex-col">

                <!-- Brand Area -->
                <div class="shrink-0 px-8 py-10">
                    <div class="flex flex-col items-center justify-center text-center">
                        <div class="text-3xl font-black tracking-tighter text-white leading-none drop-shadow-[0_0_15px_rgba(99,102,241,0.5)]">
                            Filo<span class="text-indigo-500">MERKEZ</span>
                        </div>
                        <div class="mt-3 text-[11px] font-extrabold uppercase tracking-[0.25em] text-indigo-300 whitespace-nowrap animate-pulse drop-shadow-[0_0_10px_rgba(165,180,252,0.8)]">
                            {{ $company->name ?? 'Kurumsal Sürüm' }}
                        </div>
                    </div>
                </div>

                <div class="sidebar-scroll min-h-0 flex-1 overflow-y-auto px-4 pb-12">
                    <div class="space-y-6">

                        @if($user && $user->isSuperAdmin())
                            <div class="pt-2">
                                <div class="px-4 mb-2 text-[10px] font-extrabold uppercase tracking-[0.25em] text-slate-600">Süper Admin Menü</div>
                                <div class="space-y-1">
                                    <a href="{{ route('super-admin.dashboard') }}"
                                       class="group relative flex items-center gap-4 rounded-2xl px-4 py-2.5 transition-all duration-500 {{ request()->routeIs('super-admin.dashboard') ? 'bg-white/10 shadow-[inset_0_1px_1px_rgba(255,255,255,0.1)] text-white' : 'text-slate-300 hover:bg-white/[0.05] hover:text-white' }}">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-all duration-500 {{ request()->routeIs('super-admin.dashboard') ? 'bg-gradient-to-br from-rose-500/20 to-purple-600/20 border border-rose-500/30 shadow-[0_0_20px_rgba(244,63,94,0.3)]' : 'bg-slate-800/40 border border-white/5 group-hover:bg-slate-700/50' }}">
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Control%20Knobs.png" alt="Genel Bakış" class="w-6 h-6 drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6" />
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-[14px] font-bold tracking-tight truncate">Genel Bakış</span>
                                            <span class="text-[9px] font-bold {{ request()->routeIs('super-admin.dashboard') ? 'text-rose-200/60' : 'text-slate-500' }} uppercase tracking-widest truncate">SİSTEM ÖZETİ</span>
                                        </div>
                                    </a>

                                    <a href="{{ route('super-admin.companies.index') }}"
                                       class="group relative flex items-center gap-4 rounded-2xl px-4 py-2.5 transition-all duration-500 {{ request()->routeIs('super-admin.companies.*') ? 'bg-white/10 shadow-[inset_0_1px_1px_rgba(255,255,255,0.1)] text-white' : 'text-slate-300 hover:bg-white/[0.05] hover:text-white' }}">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-all duration-500 {{ request()->routeIs('super-admin.companies.*') ? 'bg-gradient-to-br from-rose-500/20 to-purple-600/20 border border-rose-500/30 shadow-[0_0_20px_rgba(244,63,94,0.3)]' : 'bg-slate-800/40 border border-white/5 group-hover:bg-slate-700/50' }}">
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Office%20Building.png" alt="Firmalar" class="w-6 h-6 drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6" />
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-[14px] font-bold tracking-tight truncate">Firmalar</span>
                                            <span class="text-[9px] font-bold {{ request()->routeIs('super-admin.companies.*') ? 'text-rose-200/60' : 'text-slate-500' }} uppercase tracking-widest truncate">MÜŞTERİ YÖNETİMİ</span>
                                        </div>
                                    </a>

                                    <a href="{{ route('backups.index') }}"
                                       class="group relative flex items-center gap-4 rounded-2xl px-4 py-2.5 transition-all duration-500 {{ request()->routeIs('backups.*') ? 'bg-white/10 shadow-[inset_0_1px_1px_rgba(255,255,255,0.1)] text-white' : 'text-slate-300 hover:bg-white/[0.05] hover:text-white' }}">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-all duration-500 {{ request()->routeIs('backups.*') ? 'bg-gradient-to-br from-rose-500/20 to-purple-600/20 border border-rose-500/30 shadow-[0_0_20px_rgba(244,63,94,0.3)]' : 'bg-slate-800/40 border border-white/5 group-hover:bg-slate-700/50' }}">
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Floppy%20Disk.png" alt="Yedeklemeler" class="w-6 h-6 drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6" />
                                        </div>
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-[14px] font-bold tracking-tight truncate">Yedeklemeler</span>
                                            <span class="text-[9px] font-bold {{ request()->routeIs('backups.*') ? 'text-rose-200/60' : 'text-slate-500' }} uppercase tracking-widest truncate">VERİ GÜVENLİĞİ</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="pt-2">
                                <div class="px-4 mb-2 text-[10px] font-extrabold uppercase tracking-[0.25em] text-slate-600">Ana Menü</div>
                                <div class="space-y-1">

                                    @foreach($navItems as $item)
                                        @php
                                            $isActive = request()->routeIs(explode('.', $item['route'])[0].'.*') || request()->routeIs($item['route']);

                                            // Modül bazlı kontrol
                                            $moduleAccess = !$item['module'] || $user->canAccessModule($item['module']);

                                            // Yetki bazlı kontrol (eğer belirtilmişse)
                                            $permissionAccess = !($item['permission'] ?? null) || $user->hasPermission($item['permission']);

                                            $canAccess = $moduleAccess && $permissionAccess;
                                        @endphp

                                        @if($canAccess)
                                            <a href="{{ route($item['route']) }}"
                                               class="group relative flex items-center gap-4 rounded-2xl px-4 py-2.5 transition-all duration-500
                                               {{ $isActive
                                                  ? 'bg-white/10 shadow-[inset_0_1px_1px_rgba(255,255,255,0.1)] text-white'
                                                  : 'text-slate-300 hover:bg-white/[0.05] hover:text-white' }}">
                                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-all duration-500 {{ $isActive ? 'bg-gradient-to-br from-indigo-500/20 to-purple-600/20 border border-indigo-500/30 shadow-[0_0_20px_rgba(79,70,229,0.3)]' : 'bg-slate-800/40 border border-white/5 group-hover:bg-slate-700/50' }}">
                                                    <img src="{{ $item['image'] ?? 'https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Pushpin.png' }}" alt="{{ $item['label'] }}" class="w-6 h-6 drop-shadow-[0_5px_5px_rgba(0,0,0,0.5)] transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6" />
                                                </div>
                                                <div class="flex flex-col min-w-0">
                                                    <span class="text-[14px] font-bold tracking-tight truncate">{{ $item['label'] }}</span>
                                                    <span class="text-[9px] font-bold {{ $isActive ? 'text-indigo-200/60' : 'text-slate-500' }} uppercase tracking-widest truncate">{{ $item['sub'] }}</span>
                                                </div>
                                                @if($isActive)
                                                    <div class="ml-auto flex items-center">
                                                        <div class="h-2 w-2 rounded-full bg-indigo-400 shadow-[0_0_10px_rgba(129,140,248,0.8)] animate-pulse"></div>
                                                    </div>
                                                @elseif($item['route'] === 'chat.index')
                                                    <div class="ml-auto flex items-center">
                                                        <span id="sidebar-chat-badge" style="display: none;" class="flex items-center justify-center w-5 h-5 rounded-full bg-rose-500 text-white text-[10px] font-bold shadow-[0_0_10px_rgba(244,63,94,0.5)]">0</span>
                                                    </div>
                                                @endif
                                            </a>
                                        @endif
                                    @endforeach

                                </div>
                            </div>
                        @endif

                    </div>
                </div>

                <style>
        [x-cloak] { display: none !important; }
        
        /* GLOBAL YAZDIRMA AYARLARI - SADECE BELGE KALSIN */
        @media print {
            aside, header, nav, footer, .no-print, button, .sidebar-wrapper, .top-nav {
                display: none !important;
            }
            main, .content-wrapper, #app {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                display: block !important;
            }
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .mx-auto {
                max-width: 100% !important;
            }
        }
    </style>
                </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <div class="ml-72 flex-1 min-w-0 min-h-screen flex flex-col bg-slate-50">

            @php
                $licenseDays = $company ? $company->licenseDaysRemaining() : null;
            @endphp
            @if($licenseDays !== null && $licenseDays <= 7 && $licenseDays >= 0)
                <div class="bg-gradient-to-r from-rose-500 to-red-600 px-6 py-3 text-white shadow-md flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div>
                            <p class="text-sm font-black tracking-wide">LİSANS SÜRESİ UYARISI</p>
                            <p class="text-xs font-medium text-rose-100">Sistem lisansınızın bitmesine <b>{{ $licenseDays }} gün</b> kaldı. Hizmet kesintisi yaşamamak için lütfen sistem yöneticiniz ile iletişime geçiniz.</p>
                        </div>
                    </div>
                </div>
            @endif

            <main class="flex-1 p-6 lg:p-8">
                <div class="h-full rounded-[40px] border border-white bg-white/60 shadow-[0_20px_50px_-12px_rgba(0,0,0,0.08)] backdrop-blur-xl overflow-hidden flex flex-col">
                    
                    <!-- GLASS HEADER -->
                    <div class="glass-header border-b border-slate-200/40 px-8 py-6 shrink-0 relative z-[100]">
                        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <!-- Left: Title & Subtitle -->
                            <div class="flex items-center gap-6">
                                <div class="hidden h-12 w-1.5 shadow-lg rounded-full bg-gradient-to-b from-indigo-500 to-purple-600 md:block"></div>
                                <div>
                                    <h1 class="text-3xl font-black tracking-tight text-slate-900">
                                        @yield('title', 'Sistem Paneli')
                                    </h1>
                                    <p class="mt-1 text-[11px] font-bold text-slate-400 flex items-center gap-2 uppercase tracking-widest">
                                        <span class="relative flex h-2 w-2">
                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </span>
                                        @yield('subtitle', 'Operasyonel Kontrol Merkezi')
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Actions & Profile -->
                            <div class="flex items-center gap-4 sm:gap-6">
                                <!-- Premium Sistem Saati & Çıkış -->
                                <div class="hidden items-center gap-3 md:flex mr-2">
                                    <!-- Premium Clock Widget (Light Glassmorphism) -->
                                    <div class="relative group cursor-default">
                                        <div class="absolute -inset-1 rounded-full bg-gradient-to-r from-slate-200 via-white to-slate-200 opacity-50 blur-sm transition duration-500"></div>
                                        <div class="relative flex items-center gap-4 px-5 py-2.5 rounded-full bg-white/90 backdrop-blur-xl border border-slate-200/50 shadow-[0_8px_30px_rgb(0,0,0,0.04)] transition-all hover:shadow-[0_8px_30px_rgb(0,0,0,0.08)]">
                                            
                                            <!-- Calendar Icon -->
                                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-slate-50 text-slate-400 border border-slate-100 group-hover:text-indigo-500 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            </div>

                                            <div class="flex flex-col justify-center">
                                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-[0.15em] leading-none mb-1" id="clock-date">{{ \Carbon\Carbon::now()->locale('tr')->translatedFormat('j F l') }}</span>
                                                <div class="flex items-center gap-2 leading-none">
                                                    <span class="text-sm font-black text-slate-800 tracking-tight font-mono" id="clock-time">{{ now()->format('H:i:s') }}</span>
                                                </div>
                                            </div>

                                            <!-- Divider -->
                                            <div class="h-6 w-[1.5px] bg-slate-200/60 rounded-full mx-1"></div>

                                            <!-- Live Status -->
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="relative flex h-2 w-2 mb-1">
                                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span>
                                                </div>
                                                <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest leading-none">ON</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Premium Hızlı Çıkış -->
                                    <form method="POST" action="{{ route('logout') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="group relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white border border-rose-100 shadow-sm hover:shadow-rose-200 hover:shadow-lg transition-all active:scale-95" title="Güvenli Çıkış">
                                            <div class="absolute inset-0 rounded-2xl bg-gradient-to-tr from-rose-500 to-pink-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                            <svg class="relative w-5 h-5 text-rose-500 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>

                                <div class="relative group" x-data="{ open: false }">
                                    <button @click="open = !open" class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-md font-black text-white shadow-lg border border-indigo-400/50 hover:scale-105 transition-all active:scale-95 ring-2 ring-white ring-offset-2 ring-offset-slate-50">
                                        @php
                                            $nameParts = explode(' ', trim($user->name ?? 'U'));
                                            $initials = count($nameParts) >= 2 
                                                ? mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1) 
                                                : mb_substr($nameParts[0], 0, 2);
                                        @endphp
                                        <span class="relative text-sm tracking-widest">{{ mb_strtoupper($initials) }}</span>
                                        <div class="absolute -bottom-1 -right-1 h-3.5 w-3.5 rounded-full bg-emerald-500 border-2 border-white shadow-lg"></div>
                                    </button>

                                    <!-- User Dropdown -->
                                    <div x-show="open" @click.away="open = false" 
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                                         class="absolute right-0 mt-4 w-72 origin-top-right rounded-[32px] bg-white/90 backdrop-blur-2xl p-2 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.3)] ring-1 ring-slate-200/50 z-[100]" x-cloak>
                                        
                                        <div class="px-5 py-4 border-b border-slate-100/80 bg-slate-50/50 rounded-t-[24px] mb-2">
                                            <div class="text-base font-black text-slate-900 leading-tight">{{ $user->name }}</div>
                                            <div class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest mt-1">{{ $user->email }}</div>
                                        </div>
                                        
                                        <div class="space-y-1 p-1">
                                            <a href="{{ route('profile.edit') }}" class="group flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 rounded-[20px] transition-all">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 group-hover:bg-indigo-100 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                </div>
                                                Profil Ayarları
                                            </a>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="group flex w-full items-center gap-3 px-4 py-3 text-sm font-bold text-rose-600 hover:bg-rose-50 hover:text-rose-700 rounded-[20px] transition-all text-left">
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 group-hover:bg-rose-200 group-hover:text-rose-700 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                                    </div>
                                                    Sistemden Çıkış Yap
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MAIN SCROLLABLE CONTENT -->
                    <div class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50/50">
                        @yield('content')
                    </div>
                </div>
            </main>
        </div>

    </div>
</div>

@if($expiredVehicleDocuments->count())
    <div id="expiredVehicleDocumentsGlobalModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/55 p-4">
        <div class="w-full max-w-2xl rounded-[30px] bg-white shadow-2xl overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-2xl">
                        ⚠️
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900">Günü Geçen Araç Belgeleri Var</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Lütfen aşağıdaki araç belgelerini güncelleyiniz.
                        </p>
                    </div>
                </div>
            </div>

            <div class="max-h-[60vh] overflow-y-auto p-6 space-y-4">
                @foreach($expiredVehicleDocuments as $document)
                    <div class="rounded-[24px] border border-rose-200 bg-rose-50 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-base font-bold text-slate-900">
                                    {{ optional($document->documentable)->plate ?? 'Araç' }} plakalı aracınızın
                                    <span class="text-rose-700">{{ $document->document_type }}</span>
                                    belgesinin günü geçti.
                                </div>
                                <div class="mt-1 text-sm text-slate-600">
                                    Belge adı: {{ $document->document_name ?: '-' }}
                                </div>
                                <div class="mt-1 text-sm text-slate-600">
                                    Son geçerlilik tarihi:
                                    <span class="font-semibold text-rose-700">
                                        {{ optional($document->end_date)->format('d.m.Y') ?: '-' }}
                                    </span>
                                </div>
                            </div>

                            <a href="{{ route('vehicles.show', ['vehicle' => $document->documentable_id, 'tab' => 'documents']) }}"
                               class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-rose-600 to-pink-600 px-5 py-3 text-sm font-semibold text-white shadow hover:scale-[1.01] transition">
                                Belgeyi Güncelle
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-6 py-4 bg-slate-50 flex justify-end">
                <button type="button"
                        id="closeExpiredVehicleDocumentsGlobalModal"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Daha Sonra Kapat
                </button>
            </div>
        </div>
    </div>
@endif

@if($driverDocumentAlerts->count())
    <div id="driverDocumentsGlobalModal" class="fixed inset-0 z-[61] flex items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-3xl rounded-[30px] bg-white shadow-2xl overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $driverDocumentAlerts->contains(fn ($alert) => $alert['status'] === 'expired') ? 'bg-rose-100' : 'bg-amber-100' }} text-2xl">
                        📄
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900">Personel Belge Uyarıları Var</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Süresi geçen veya yakında bitecek personel belgelerini güncelleyiniz.
                        </p>
                    </div>
                </div>

                {{-- Uyarı eşik bilgileri --}}
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-bold text-indigo-700 ring-1 ring-indigo-200">
                        🪪 Ehliyet / SRC → 1 ay önceden uyarı
                    </span>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-[10px] font-bold text-amber-700 ring-1 ring-amber-200">
                        📋 Adli Sicil / Psikoteknik / Diğer → 1 hafta önceden uyarı
                    </span>
                </div>
            </div>

            <div class="max-h-[65vh] overflow-y-auto p-6 space-y-4">
                @foreach($driverDocumentAlerts as $alert)
                    @php
                        $isExpired = $alert['status'] === 'expired';
                        $isUrgent = ($alert['urgency'] ?? 'medium') === 'high';
                        $borderClass = $isExpired ? 'border-rose-200 bg-rose-50' : ($isUrgent ? 'border-amber-200 bg-amber-50' : 'border-orange-200 bg-orange-50');
                        $textClass = $isExpired ? 'text-rose-700' : ($isUrgent ? 'text-amber-700' : 'text-orange-700');
                        $btnClass = $isExpired ? 'bg-gradient-to-r from-rose-600 to-pink-600' : ($isUrgent ? 'bg-gradient-to-r from-amber-500 to-orange-500' : 'bg-gradient-to-r from-orange-400 to-amber-500');
                    @endphp
                    <div class="rounded-[24px] border {{ $borderClass }} p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-base font-bold text-slate-900">
                                    <span class="{{ $textClass }}">
                                        {{ $alert['driver_name'] }}
                                    </span>
                                    adlı personele ait
                                    <span class="{{ $textClass }}">
                                        {{ $alert['document_type'] }}
                                    </span>
                                    belgesi
                                    {{ $isExpired ? 'süresi dolmuş durumda.' : 'bitmek üzere.' }}
                                </div>

                                <div class="mt-2 text-sm text-slate-600">
                                    Son geçerlilik tarihi:
                                    <span class="font-semibold {{ $textClass }}">
                                        {{ \Illuminate\Support\Carbon::parse($alert['end_date'])->format('d.m.Y') }}
                                    </span>
                                </div>

                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-sm font-semibold {{ $textClass }}">
                                        @if($isExpired)
                                            {{ abs((int) $alert['remaining_days']) }} gün önce süresi doldu
                                        @else
                                            {{ (int) $alert['remaining_days'] }} gün kaldı
                                        @endif
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-500 uppercase tracking-wider">
                                        eşik: {{ $alert['threshold_days'] ?? 7 }} gün
                                    </span>
                                </div>
                            </div>

                            <a href="{{ $alert['route'] }}"
                               class="inline-flex items-center justify-center rounded-2xl {{ $btnClass }} px-5 py-3 text-sm font-semibold text-white shadow hover:scale-[1.01] transition">
                                Belgeyi Güncelle
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-6 py-4 bg-slate-50 flex justify-end">
                <button type="button"
                        id="closeDriverDocumentsGlobalModal"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Daha Sonra Kapat
                </button>
            </div>
        </div>
    </div>
@endif

@if(isset($criticalMaintenances) && $criticalMaintenances->count())
    <div id="criticalMaintenancesGlobalModal" class="fixed inset-0 z-[62] flex items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-3xl rounded-[30px] bg-white shadow-2xl overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $criticalMaintenances->contains(fn ($m) => $m['critical']) ? 'bg-rose-100' : 'bg-amber-100' }} text-2xl">
                        🛠️
                    </div>
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900">Yaklaşan / Geciken Bakım Uyarıları</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Bakım zamanı gelmiş veya gecikmiş araçların listesi.
                        </p>
                    </div>
                </div>
            </div>

            <div class="max-h-[65vh] overflow-y-auto p-6 space-y-4">
                @foreach($criticalMaintenances as $alert)
                    <div class="rounded-[24px] border {{ $alert['critical'] ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50' }} p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-base font-bold text-slate-900">
                                    <span class="{{ $alert['critical'] ? 'text-rose-700' : 'text-amber-700' }}">
                                        {{ $alert['plate'] }}
                                    </span>
                                    plakalı aracın
                                    <span class="{{ $alert['critical'] ? 'text-rose-700' : 'text-amber-700' }}">
                                        {{ $alert['type'] }}
                                    </span>
                                    zamanı
                                    {{ $alert['critical'] ? 'geçti!' : 'yaklaştı.' }}
                                </div>

                                <div class="mt-2 text-sm font-semibold {{ $alert['critical'] ? 'text-rose-700' : 'text-amber-700' }}">
                                    @if($alert['critical'])
                                        {{ number_format(abs($alert['remaining']), 0, ',', '.') }} KM gecikme var! KRİTİK DURUM.
                                    @else
                                        {{ number_format($alert['remaining'], 0, ',', '.') }} KM kaldı.
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('vehicles.show', ['vehicle' => $alert['vehicle_id'], 'tab' => 'maintenances']) }}"
                               class="inline-flex items-center justify-center rounded-2xl {{ $alert['critical'] ? 'bg-gradient-to-r from-rose-600 to-pink-600' : 'bg-gradient-to-r from-amber-500 to-orange-500' }} px-5 py-3 text-sm font-semibold text-white shadow hover:scale-[1.01] transition">
                                Bakım Ekle
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-6 py-4 bg-slate-50 flex justify-end">
                <button type="button"
                        id="closeCriticalMaintenancesGlobalModal"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    Daha Sonra Kapat
                </button>
            </div>
        </div>
    </div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        function updateGlobalClock() {
            const now = new Date();
            const dateEl = document.getElementById('clock-date');
            const timeEl = document.getElementById('clock-time');
            if (dateEl && timeEl) {
                dateEl.innerText = now.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' });
                timeEl.innerText = now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
        setInterval(updateGlobalClock, 1000);
        updateGlobalClock();

        const vehicleModal = document.getElementById('expiredVehicleDocumentsGlobalModal');
        const vehicleCloseBtn = document.getElementById('closeExpiredVehicleDocumentsGlobalModal');

        // Oturumda daha önce kapatıldıysa gösterme
        if (vehicleModal && sessionStorage.getItem('vehicleDocModalDismissed')) {
            vehicleModal.style.display = 'none';
        }

        if (vehicleModal && vehicleCloseBtn) {
            vehicleCloseBtn.addEventListener('click', function () {
                vehicleModal.style.display = 'none';
                sessionStorage.setItem('vehicleDocModalDismissed', '1');
            });

            vehicleModal.addEventListener('click', function (e) {
                if (e.target === vehicleModal) {
                    vehicleModal.style.display = 'none';
                    sessionStorage.setItem('vehicleDocModalDismissed', '1');
                }
            });
        }

        const driverModal = document.getElementById('driverDocumentsGlobalModal');
        const driverCloseBtn = document.getElementById('closeDriverDocumentsGlobalModal');

        // Oturumda daha önce kapatıldıysa gösterme
        if (driverModal && sessionStorage.getItem('driverDocModalDismissed')) {
            driverModal.style.display = 'none';
        }

        if (driverModal && driverCloseBtn) {
            driverCloseBtn.addEventListener('click', function () {
                driverModal.style.display = 'none';
                sessionStorage.setItem('driverDocModalDismissed', '1');
            });

            driverModal.addEventListener('click', function (e) {
                if (e.target === driverModal) {
                    driverModal.style.display = 'none';
                    sessionStorage.setItem('driverDocModalDismissed', '1');
                }
            });
        }

        const maintenanceModal = document.getElementById('criticalMaintenancesGlobalModal');
        const maintenanceCloseBtn = document.getElementById('closeCriticalMaintenancesGlobalModal');

        // Oturumda daha önce kapatıldıysa gösterme
        if (maintenanceModal && sessionStorage.getItem('maintenanceAlertModalDismissed')) {
            maintenanceModal.style.display = 'none';
        }

        if (maintenanceModal && maintenanceCloseBtn) {
            maintenanceCloseBtn.addEventListener('click', function () {
                maintenanceModal.style.display = 'none';
                sessionStorage.setItem('maintenanceAlertModalDismissed', '1');
            });

            maintenanceModal.addEventListener('click', function (e) {
                if (e.target === maintenanceModal) {
                    maintenanceModal.style.display = 'none';
                    sessionStorage.setItem('maintenanceAlertModalDismissed', '1');
                }
            });
        }
    });
</script>

{{-- Global Chat Notification --}}
@auth
<audio id="global-chat-notification-sound" src="data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQQAAAAAAAD//w==" preload="auto"></audio>

<div id="global-chat-toast" class="fixed bottom-6 right-6 z-50 transform transition-all duration-300 translate-y-20 opacity-0 bg-white rounded-2xl shadow-2xl border border-slate-100 p-4 w-80" style="display: none;">
    <div class="flex items-start gap-3 cursor-pointer" onclick="window.location.href='{{ route('chat.index') }}'">
        <div class="w-10 h-10 rounded-full bg-[#00a884] text-white flex items-center justify-center font-bold shadow-lg">
            💬
        </div>
        <div class="flex-1">
            <h4 class="text-sm font-bold text-slate-800" id="global-chat-sender">PilotChat</h4>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-2" id="global-chat-body">Mesaj içeriği...</p>
        </div>
        <button onclick="event.stopPropagation(); document.getElementById('global-chat-toast').classList.replace('translate-y-0', 'translate-y-20'); document.getElementById('global-chat-toast').classList.replace('opacity-100', 'opacity-0'); setTimeout(() => document.getElementById('global-chat-toast').style.display = 'none', 300); " class="text-slate-400 hover:text-slate-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let lastUnreadCount = 0;
        
        // Sadece chat sayfasında değilsek polling yap (chat sayfası kendi içinde güncelleniyor)
        if (window.location.pathname.indexOf('/chat') === -1) {
            setInterval(() => {
                fetch('{{ route("chat.unread") }}')
                    .then(res => res.json())
                    .then(data => {
                        if (data.count > lastUnreadCount && data.latest) {
                            // Sesi Çal
                            const audio = document.getElementById('global-chat-notification-sound');
                            if (audio) {
                                audio.currentTime = 0;
                                audio.play().catch(e => console.log('Audio blocked:', e));
                            }
                            
                            // Toast Göster
                            document.getElementById('global-chat-sender').textContent = data.latest.sender;
                            document.getElementById('global-chat-body').textContent = data.latest.body;
                            
                            const toast = document.getElementById('global-chat-toast');
                            toast.style.display = 'block';
                            setTimeout(() => {
                                toast.classList.remove('translate-y-20', 'opacity-0');
                                toast.classList.add('translate-y-0', 'opacity-100');
                            }, 50);
                            
                            // 5 saniye sonra gizle
                            setTimeout(() => {
                                toast.classList.remove('translate-y-0', 'opacity-100');
                                toast.classList.add('translate-y-20', 'opacity-0');
                                setTimeout(() => toast.style.display = 'none', 300);
                            }, 5000);
                        }
                        
                        lastUnreadCount = data.count;
                        
                        // Sidebar badge güncellemesi
                        const badge = document.getElementById('sidebar-chat-badge');
                        if (badge) {
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'flex';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(e => console.log(e));
            }, 5000);
        }
    });
</script>
@endauth

</body>
</html>
