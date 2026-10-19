@extends('layouts.app')

@section('title', 'Araçlar')
@section('subtitle', 'Tüm Filo Listesi')

@section('content')

<div class="space-y-6" x-data="{ 
    assignOpen: false, 
    importOpen: false, 
    vehicleId: null, 
    vehiclePlate: '', 
    driverSearch: '',
    columnsOpen: false,
    columns: JSON.parse(localStorage.getItem('vehicle_columns') || '{&quot;plate&quot;:true,&quot;type_year&quot;:true,&quot;status&quot;:true,&quot;personnel&quot;:true,&quot;km&quot;:true,&quot;engine_no&quot;:false,&quot;chassis_no&quot;:false,&quot;license_serial&quot;:false,&quot;license_owner&quot;:false,&quot;seat_count&quot;:false}'),
    saveColumns() { localStorage.setItem('vehicle_columns', JSON.stringify(this.columns)); },
    selectedIds: [],
    get allIds() { return {{ json_encode($vehicles->pluck('id')->toArray()) }}; },
    get allSelected() { return this.allIds.length > 0 && this.selectedIds.length === this.allIds.length; },
    toggleAll() {
        if (this.allSelected) {
            this.selectedIds = [];
        } else {
            this.selectedIds = [...this.allIds];
        }
    }
}">
    
    {{-- Bildirimler --}}
    @if(session('success'))
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3 shadow-sm">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white text-[10px]">✓</span>
            <span class="text-sm font-bold text-emerald-800">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-center gap-3 shadow-sm">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-500 text-white text-[10px]">X</span>
            <span class="text-sm font-bold text-rose-800">{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4 shadow-sm">
            <div class="flex items-center gap-3 mb-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white text-[10px]">!</span>
                <span class="text-sm font-bold text-amber-800">Lütfen aşağıdaki hataları düzeltin:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-bold text-amber-700 ml-9">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-5 gap-6 mb-6">
        
        {{-- Toplam Araç --}}
        <a href="{{ route('vehicles.index') }}" class="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-indigo-500 to-blue-600 p-6 shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl z-0">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Bus.png" alt="Toplam Araç" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex flex-col justify-between h-full z-10">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-white uppercase tracking-widest bg-white/20 px-3 py-1.5 rounded-xl">Toplam Araç Sayısı</span>
                </div>
                <div class="mt-6">
                    <div class="text-3xl font-black text-white">{{ $kpi['total'] }}</div>
                </div>
            </div>
        </a>

        {{-- Şoförsüz Araçlar --}}
        <a href="{{ route('vehicles.index', ['filter' => 'no_driver']) }}" class="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-purple-500 to-fuchsia-600 p-6 shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl z-0">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Seat.png" alt="Personel Yok" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex flex-col justify-between h-full z-10">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-white uppercase tracking-widest bg-white/20 px-3 py-1.5 rounded-xl">Şoförü Olmayan (Boşta)</span>
                </div>
                <div class="mt-6">
                    <div class="text-3xl font-black text-white">{{ $kpi['no_driver'] }}</div>
                </div>
            </div>
        </a>

        {{-- Muayenesi Yaklaşan --}}
        <a href="{{ route('vehicles.index', ['filter' => 'upcoming_inspection']) }}" class="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-amber-500 to-orange-500 p-6 shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl z-0">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Wrench.png" alt="Muayene" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex flex-col justify-between h-full z-10">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-white uppercase tracking-widest bg-white/20 px-3 py-1.5 rounded-xl">Muayenesi Yaklaşan</span>
                </div>
                <div class="mt-6">
                    <div class="text-3xl font-black text-white">{{ $kpi['upcoming_inspection'] }}</div>
                </div>
            </div>
        </a>

        {{-- Sigortası Yaklaşan --}}
        <a href="{{ route('vehicles.index', ['filter' => 'upcoming_insurance']) }}" class="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-rose-500 to-pink-600 p-6 shadow-xl hover:shadow-2xl hover:-translate-y-1 transition-all duration-300">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl z-0">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Shield.png" alt="Sigorta" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex flex-col justify-between h-full z-10">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black text-white uppercase tracking-widest bg-white/20 px-3 py-1.5 rounded-xl">Sigortası Yaklaşan</span>
                </div>
                <div class="mt-6">
                    <div class="text-3xl font-black text-white">{{ $kpi['upcoming_insurance'] }}</div>
                </div>
            </div>
        </a>

        {{-- Animasyonlu Araç Tipi --}}
        <div x-data="{ 
                types: {{ json_encode($kpi['types']) }}, 
                keys: Object.keys({{ json_encode($kpi['types']) }}).length ? Object.keys({{ json_encode($kpi['types']) }}) : ['Belirsiz'],
                currentIndex: 0,
                colors: ['text-white bg-white/20', 'text-white bg-white/25', 'text-white bg-white/30', 'text-white bg-white/10'],
                init() { 
                    if(this.keys.length > 1) {
                        setInterval(() => { this.currentIndex = (this.currentIndex + 1) % this.keys.length; }, 5000);
                    }
                }
             }" 
             class="group relative overflow-hidden rounded-[32px] bg-gradient-to-br from-emerald-500 to-teal-500 p-6 shadow-xl hover:shadow-2xl transition-all duration-300">
            <div class="absolute -right-2 -bottom-2 opacity-100 group-hover:scale-110 group-hover:rotate-6 transition-transform duration-700 drop-shadow-2xl z-0">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Automobile.png" alt="Araç Tipi" class="w-24 h-24 drop-shadow-2xl" />
            </div>
            <div class="relative flex flex-col justify-between h-full z-10">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest transition-all duration-500 px-3 py-1.5 rounded-xl" :class="colors[currentIndex % colors.length]">
                        <span x-text="'Toplam ' + keys[currentIndex] + ' Sayısı'"></span>
                    </span>
                </div>
                <div class="mt-6">
                    <div class="text-3xl font-black text-white transition-all duration-500" x-text="types[keys[currentIndex]] || 0"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- Üst Bar ve Filtreleme --}}
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 bg-white p-4 rounded-3xl border border-slate-100 shadow-sm">
        
        {{-- Sol Filtreleme --}}
        <form method="GET" action="{{ route('vehicles.index') }}" class="flex flex-col sm:flex-row items-center gap-3 w-full xl:w-auto">
            <div class="relative w-full sm:w-80">
                <div class="absolute inset-y-0 left-4 flex items-center text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Plaka veya marka ara..." 
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-4 py-3 text-sm font-bold text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            </div>
            
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <select name="type" class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 appearance-none min-w-[140px]">
                    <option value="">Tüm Tipler</option>
                    <option value="Minibüs" @selected(request('type') == 'Minibüs')>Minibüs</option>
                    <option value="Midibüs" @selected(request('type') == 'Midibüs')>Midibüs</option>
                    <option value="Otobüs" @selected(request('type') == 'Otobüs')>Otobüs</option>
                    <option value="Binek Araç" @selected(request('type') == 'Binek Araç')>Binek Araç</option>
                </select>

                <button type="submit" class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 p-3 rounded-2xl transition-all font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                </button>
                
                <a href="{{ route('vehicles.export.excel') }}" title="Excel'e Aktar" class="bg-emerald-50 text-emerald-600 hover:bg-emerald-100 p-3 rounded-2xl transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                </a>
            </div>
        </form>

        {{-- Sağ Buton --}}
        @if(auth()->user()->hasPermission('vehicles.create'))
        <div class="flex items-center gap-3 w-full xl:w-auto">
            
            {{-- Sütun Filtresi --}}
            <div class="relative">
                <button @click="columnsOpen = !columnsOpen" type="button" class="flex items-center justify-center gap-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 px-4 py-3 rounded-2xl font-black text-xs transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                    FİLTRE
                </button>
                <div x-show="columnsOpen" @click.away="columnsOpen = false" x-cloak class="absolute right-0 mt-2 w-56 bg-white border border-slate-100 rounded-3xl shadow-xl z-50 p-3">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 px-2">Görünecek Sütunlar</div>
                    <div class="space-y-1">
                        <template x-for="(label, key) in {'plate': 'Plaka / Araç', 'type_year': 'Tip / Yıl', 'status': 'Durum', 'personnel': 'Personel', 'km': 'Güncel KM', 'engine_no': 'Motor No', 'chassis_no': 'Şasi No', 'license_serial': 'Seri No', 'license_owner': 'Ruhsat Sahibi', 'seat_count': 'Koltuk Sayısı'}">
                            <label class="flex items-center gap-3 px-2 py-2 hover:bg-slate-50 rounded-xl cursor-pointer transition-colors group">
                                <input type="checkbox" x-model="columns[key]" @change="saveColumns()" class="w-4 h-4 text-indigo-600 rounded-md border-slate-300 focus:ring-indigo-500 transition-all">
                                <span class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-600 transition-colors uppercase tracking-wide" x-text="label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <button @click="importOpen = true" class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-3 rounded-2xl font-black text-sm transition-all shadow-sm hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                TOPLU EKLE
            </button>
            <a href="{{ route('vehicles.create') }}" class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black text-sm transition-all shadow-lg shadow-indigo-200 hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                YENİ ARAÇ EKLE
            </a>
        </div>
        @endif
    </div>

    {{-- Bulk Action Toolbar --}}
    <div x-show="selectedIds.length > 0" x-transition.opacity class="bg-indigo-900 rounded-[24px] p-4 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl border border-indigo-700/50 relative overflow-hidden group mb-6">
        <div class="absolute inset-0 bg-gradient-to-r from-indigo-600/20 to-purple-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
        <div class="flex items-center gap-4 relative z-10">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-white font-black text-xl shadow-inner backdrop-blur-sm">
                <span x-text="selectedIds.length"></span>
            </div>
            <div>
                <h4 class="text-white font-black text-lg">Araç Seçildi</h4>
                <p class="text-indigo-200 text-xs font-bold mt-0.5">Toplu işlemler için araçlar işaretlendi.</p>
            </div>
        </div>

        <div class="flex items-center gap-3 relative z-10">
            <button @click="selectedIds = []" class="px-5 py-3 rounded-2xl bg-white/5 hover:bg-white/10 text-white font-bold text-sm border border-white/10 transition-all hover:-translate-y-0.5">
                Seçimi Temizle
            </button>
            <form action="{{ route('vehicles.bulk-delete') }}" method="POST" class="inline" onsubmit="return confirm('Seçilen ' + selectedIds.length + ' aracı silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <button type="submit" class="flex items-center gap-2 px-6 py-3 rounded-2xl bg-rose-500 hover:bg-rose-600 text-white font-black text-sm shadow-lg shadow-rose-500/20 transition-all hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Seçilenleri Sil
                </button>
            </form>
        </div>
    </div>

    {{-- Tablo --}}
    <div class="bg-white rounded-[32px] border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <th class="w-12 p-6 text-center">
                            <input type="checkbox" x-model="allSelected" @click="toggleAll()" class="w-5 h-5 rounded-[6px] border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer transition-all hover:scale-110">
                        </th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.plate">Plaka / Araç</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.type_year">Tip / Yıl</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.status">Durum</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.personnel">Personel</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.km">
                            @if(request('filter') === 'upcoming_inspection')
                                Güncel KM / Muayene
                            @elseif(request('filter') === 'upcoming_insurance')
                                Güncel KM / Sigorta
                            @else
                                Güncel KM
                            @endif
                        </th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.engine_no">Motor No</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.chassis_no">Şasi No</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.license_serial">Seri No</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.license_owner">Ruhsat Sahibi</th>
                        <th class="p-6 whitespace-nowrap" x-show="columns.seat_count">Koltuk</th>
                        <th class="p-6 whitespace-nowrap text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($vehicles as $vehicle)
                        @php
                            $primaryDriver = $vehicle->drivers()->latest()->first();
                            $latestFuel = $vehicle->fuels->first();
                            $displayKm = $latestFuel->km ?? $vehicle->current_km ?? 0;
                            $mStatus = $vehicle->maintenance_status;
                            $isCritical = $mStatus['has_setting'] && $mStatus['oil_remaining'] < 2000;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors group">
                            
                            {{-- Checkbox --}}
                            <td class="p-6 text-center">
                                <input type="checkbox" value="{{ $vehicle->id }}" x-model="selectedIds" class="w-5 h-5 rounded-[6px] border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer transition-all hover:scale-110">
                            </td>

                            {{-- Plaka / Araç --}}
                            <td class="p-6" x-show="columns.plate">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 shrink-0 group-hover:bg-indigo-50 group-hover:border-indigo-100 group-hover:text-indigo-600 transition-colors">
                                        @if($vehicle->vehicle_type === 'Otobüs')
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Bus.png" alt="Otobüs" class="w-10 h-10 drop-shadow-md" />
                                        @elseif($vehicle->vehicle_type === 'Minibüs' || $vehicle->vehicle_type === 'Midibüs')
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Minibus.png" alt="Minibüs" class="w-10 h-10 drop-shadow-md" />
                                        @else
                                            <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Automobile.png" alt="Binek" class="w-10 h-10 drop-shadow-md" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-[15px] font-black text-slate-800">{{ $vehicle->plate }}</div>
                                        <div class="text-[11px] font-bold text-slate-400 uppercase mt-0.5">{{ $vehicle->brand }} {{ $vehicle->model }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Tip / Yıl --}}
                            <td class="p-6" x-show="columns.type_year">
                                <div class="flex flex-col items-start gap-1">
                                    <span class="inline-flex px-2 py-1 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-black uppercase">{{ $vehicle->vehicle_type }}</span>
                                    <span class="text-xs font-bold text-slate-500">{{ $vehicle->model_year }} Model</span>
                                </div>
                            </td>

                            {{-- Durum --}}
                            <td class="p-6" x-show="columns.status">
                                @if($vehicle->is_active)
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-black uppercase">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Aktif
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 text-[10px] font-black uppercase">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Pasif
                                    </div>
                                @endif
                                
                                @if($mStatus['has_setting'] && $isCritical)
                                    <div class="mt-2 inline-flex items-center gap-1 text-rose-500 text-[10px] font-black uppercase">
                                        🚨 Bakım Yaklaştı
                                    </div>
                                @endif
                            </td>

                            {{-- Personel --}}
                            <td class="p-6" x-show="columns.personnel">
                                <button type="button" 
                                        @if(auth()->user()->hasPermission('vehicles.edit'))
                                        @click="assignOpen = true; vehicleId = {{ $vehicle->id }}; vehiclePlate = '{{ $vehicle->plate }}'; driverSearch = ''"
                                        @endif
                                        class="flex items-center gap-3 text-left {{ auth()->user()->hasPermission('vehicles.edit') ? 'hover:bg-slate-100 p-2 -ml-2 rounded-xl transition-colors' : '' }}">
                                    <div class="h-8 w-8 rounded-full bg-slate-200 border border-white flex items-center justify-center text-[10px] font-black text-slate-500 shrink-0 shadow-sm">
                                        {{ $primaryDriver ? mb_substr($primaryDriver->full_name ?? 'A', 0, 1) : '?' }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold {{ $primaryDriver ? 'text-slate-700' : 'text-slate-400 italic' }} truncate max-w-[120px]">
                                            {{ $primaryDriver->full_name ?? 'Personel Yok' }}
                                        </span>
                                        <span class="text-[9px] font-bold text-indigo-500 uppercase">Ata / Değiştir</span>
                                    </div>
                                </button>
                            </td>

                            {{-- Güncel KM & Muayene / Sigorta Durumu --}}
                            <td class="p-6" x-show="columns.km">
                                <div class="flex flex-col items-start gap-3">
                                    <div class="w-full min-w-[120px]">
                                        <div class="flex items-end gap-1 mb-1.5">
                                            <div class="text-sm font-black text-slate-800 font-mono tracking-tight">{{ number_format($displayKm, 0, ',', '.') }}</div>
                                            <div class="text-[9px] text-slate-400 font-black uppercase mb-0.5">KM</div>
                                        </div>
                                        
                                        @php
                                            $kmPercentage = min(100, ($displayKm / 1000000) * 100);
                                        @endphp
                                        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden shadow-inner">
                                            <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all duration-1000" style="width: {{ $kmPercentage }}%"></div>
                                        </div>

                                        @if($mStatus['has_setting'])
                                            <div class="text-[9px] font-bold mt-1.5 uppercase tracking-wider {{ $isCritical ? 'text-rose-500' : 'text-slate-400' }}">Kalan Bakım: {{ number_format($mStatus['oil_remaining'], 0, ',', '.') }} KM</div>
                                        @endif
                                    </div>
                                    
                                    @if(request('filter') === 'upcoming_inspection')
                                        @php
                                            $inspectionDate = $vehicle->alertDate('inspection');
                                        @endphp
                                        @if($inspectionDate)
                                            @php
                                                $diffDays = now()->startOfDay()->diffInDays($inspectionDate->startOfDay(), false);
                                                $isOverdue = $diffDays < 0;
                                                $absDays = abs(intval($diffDays));
                                            @endphp
                                            <div class="p-3 rounded-2xl flex flex-col gap-2 w-48 {{ $isOverdue ? 'bg-rose-50 border border-rose-100' : 'bg-amber-50 border border-amber-100' }} shadow-sm">
                                                <div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                                        Son Tarih: {{ $inspectionDate->format('d.m.Y') }}
                                                    </div>
                                                    <div class="text-[11px] font-black mt-0.5 {{ $isOverdue ? 'text-rose-600' : 'text-amber-600' }}">
                                                        {{ $isOverdue ? "$absDays GÜN GECİKTİ!" : "$absDays GÜN KALDI" }}
                                                    </div>
                                                </div>
                                                
                                            <div class="flex flex-col gap-1.5 mt-1" x-data="{ copyPlate: false, copySerial: false }">
                                                <div class="flex gap-1.5">
                                                    <button type="button" @click="navigator.clipboard.writeText('{{ $vehicle->plate }}'); copyPlate = true; setTimeout(() => copyPlate = false, 2000);" 
                                                            class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-[8px] font-black uppercase text-slate-600 hover:bg-slate-50 transition-colors shadow-sm"
                                                            title="Plakayı Kopyala">
                                                        <span x-show="!copyPlate">📋 Plaka</span>
                                                        <span x-show="copyPlate" class="text-emerald-600" x-cloak>✓ Alındı</span>
                                                    </button>
                                                    <button type="button" @click="navigator.clipboard.writeText('{{ $vehicle->license_serial_no }}'); copySerial = true; setTimeout(() => copySerial = false, 2000);" 
                                                            class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-[8px] font-black uppercase text-slate-600 hover:bg-slate-50 transition-colors shadow-sm"
                                                            title="Seri No Kopyala">
                                                        <span x-show="!copySerial">📋 Seri No</span>
                                                        <span x-show="copySerial" class="text-emerald-600" x-cloak>✓ Alındı</span>
                                                    </button>
                                                </div>
                                                <a href="https://www.tuvturk.com.tr/hizmetlerimiz/hizli-islemler/arac-muayene-randevusu-alma" 
                                                   target="_blank"
                                                   class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 text-white text-[9px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-500/30 animate-pulse hover:animate-none group">
                                                    <span>RANDEVU AL</span>
                                                    <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </a>
                                            </div>
                                        </div>
                                        @endif
                                    @endif

                                    @if(request('filter') === 'upcoming_insurance')
                                        @php
                                            $insuranceDate = $vehicle->alertDate('insurance');
                                        @endphp
                                        @if($insuranceDate)
                                            @php
                                                $diffDays = now()->startOfDay()->diffInDays($insuranceDate->startOfDay(), false);
                                                $isOverdue = $diffDays < 0;
                                                $absDays = abs(intval($diffDays));
                                            @endphp
                                            <div class="p-3 rounded-2xl flex flex-col gap-2 w-48 {{ $isOverdue ? 'bg-rose-50 border border-rose-100' : 'bg-fuchsia-50 border border-fuchsia-100' }} shadow-sm">
                                                <div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest {{ $isOverdue ? 'text-rose-600' : 'text-fuchsia-600' }}">
                                                        Poliçe Bitiş: {{ $insuranceDate->format('d.m.Y') }}
                                                    </div>
                                                    <div class="text-[11px] font-black mt-0.5 {{ $isOverdue ? 'text-rose-600' : 'text-fuchsia-600' }}">
                                                        {{ $isOverdue ? "$absDays GÜN GECİKTİ!" : "$absDays GÜN KALDI" }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>

                            {{-- Ekstra Opsiyonel Sütunlar --}}
                            <td class="p-6 text-[11px] font-bold text-slate-600" x-show="columns.engine_no">{{ $vehicle->engine_no ?: '-' }}</td>
                            <td class="p-6 text-[11px] font-bold text-slate-600" x-show="columns.chassis_no">{{ $vehicle->chassis_no ?: '-' }}</td>
                            <td class="p-6 text-[11px] font-bold text-slate-600" x-show="columns.license_serial">{{ $vehicle->license_serial_no ?: '-' }}</td>
                            <td class="p-6 text-[11px] font-bold text-slate-600" x-show="columns.license_owner">{{ $vehicle->license_owner ?: '-' }}</td>
                            <td class="p-6 text-[11px] font-bold text-slate-600" x-show="columns.seat_count">{{ $vehicle->seat_count ?: '-' }}</td>

                            {{-- İşlemler --}}
                            <td class="p-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('vehicles.show', $vehicle) }}" class="p-2 rounded-xl bg-slate-50 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors border border-slate-100" title="Detay">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    
                                    @if(auth()->user()->hasPermission('vehicles.edit'))
                                        <a href="{{ route('vehicles.edit', $vehicle) }}" class="p-2 rounded-xl bg-slate-50 text-slate-500 hover:bg-amber-50 hover:text-amber-600 transition-colors border border-slate-100" title="Düzenle">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @endif

                                    @if(auth()->user()->hasPermission('vehicles.delete'))
                                        <form action="{{ route('vehicles.destroy', $vehicle) }}" method="POST" class="inline-block m-0" onsubmit="return confirm('Bu aracı silmek istediğinize emin misiniz?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-2 rounded-xl bg-slate-50 text-slate-500 hover:bg-rose-50 hover:text-rose-600 transition-colors border border-slate-100" title="Sil">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400">
                                <div class="mx-auto flex items-center justify-center mb-6">
                                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Magnifying%20Glass%20Tilted%20Right.png" alt="Bulunamadı" class="w-24 h-24  drop-shadow-xl" />
                                </div>
                                <div class="text-sm font-bold uppercase tracking-widest">Kayıt Bulunamadı</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Pagination --}}
        @if(method_exists($vehicles, 'links') && $vehicles->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>

    {{-- Personel Atama Modalı (Alpine ile çalışır) --}}
    <div x-show="assignOpen" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
         x-transition.opacity
         @click.self="assignOpen = false">
        
        <div class="w-full max-w-lg bg-white rounded-[32px] shadow-2xl border border-slate-100 overflow-hidden flex flex-col max-h-[90vh]"
             x-show="assignOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100 shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-800 tracking-tight">Personel Ata</h3>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><span class="text-indigo-600" x-text="vehiclePlate"></span> için operatör seçimi</p>
                    </div>
                </div>
                <button @click="assignOpen = false" class="w-10 h-10 rounded-2xl bg-white border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition-all shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-4 shrink-0 bg-white border-b border-slate-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-4 flex items-center text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" x-model="driverSearch" placeholder="İsim veya telefon ara..." class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:border-indigo-500 focus:ring-indigo-500/20 transition-all">
                </div>
            </div>

            <div class="overflow-y-auto p-2 min-h-[200px] flex-1">
                @forelse($availableDrivers as $d)
                    @php
                        $assignedBadge = $d->vehicle_id ? 'Meşgul' : 'Uygun';
                        $badgeClass = $d->vehicle_id ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100';
                    @endphp
                    <div x-show="driverSearch === '' || '{{ strtolower(addslashes($d->full_name ?? '')) }}'.includes(driverSearch.toLowerCase()) || '{{ strtolower(addslashes($d->phone ?? '')) }}'.includes(driverSearch.toLowerCase())"
                         class="p-1">
                        <form method="POST" :action="`{{ url('/vehicles') }}/${vehicleId}/assign-driver`">
                            @csrf
                            <input type="hidden" name="driver_id" value="{{ $d->id }}">
                            <button type="submit" class="w-full flex items-center justify-between p-3 rounded-2xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all group">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center text-[11px] font-black group-hover:bg-indigo-50 group-hover:text-indigo-600 group-hover:border-indigo-100 transition-all shadow-sm">
                                        {{ mb_substr($d->full_name ?? '?', 0, 1) }}
                                    </div>
                                    <div class="text-left">
                                        <div class="text-sm font-black text-slate-800">{{ $d->full_name }}</div>
                                        <div class="text-xs font-bold text-slate-400">{{ $d->phone ?: 'Numara Yok' }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="px-2 py-1 rounded-lg text-[9px] font-black uppercase {{ $badgeClass }} border">{{ $assignedBadge }}</span>
                                    <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </div>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-500 mb-4">Sistemde atanabilir personel bulunamadı.</p>
                        @if(auth()->user()->hasPermission('drivers.create'))
                            <a href="{{ route('drivers.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-black uppercase tracking-widest hover:bg-indigo-100 transition-colors">YENİ EKLE</a>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    {{-- Toplu Araç Ekle Modal --}}
    @if(auth()->user()->hasPermission('vehicles.create'))
    <div x-show="importOpen" 
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
        
        <div x-show="importOpen" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
             @click="importOpen = false"></div>

        <div x-show="importOpen" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative bg-white rounded-[32px] shadow-2xl overflow-hidden w-full max-w-md transform transition-all border border-slate-100">
            
            {{-- Header --}}
            <div class="px-8 py-6 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-black text-slate-800">Toplu Araç Ekle</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">Excel Dosyası ile İçe Aktar</p>
                </div>
                <button @click="importOpen = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Form --}}
            <form action="{{ route('vehicles.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="p-8 space-y-6">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5">
                        <div class="flex items-start gap-4">
                            <div class="h-10 w-10 shrink-0 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-indigo-900 mb-1">Örnek Şablonu İndirin</h4>
                                <p class="text-[11px] font-bold text-indigo-700/70 mb-3 leading-relaxed">
                                    Araçlarınızı sisteme sorunsuz aktarmak için öncelikle örnek Excel şablonunu indirin ve doğru formatta doldurun.
                                </p>
                                <a href="{{ route('vehicles.import.template') }}" class="inline-flex items-center gap-1.5 text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-widest bg-white px-3 py-1.5 rounded-lg border border-indigo-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    ŞABLONU İNDİR
                                </a>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2">Excel Dosyası (.xlsx, .xls, .csv)</label>
                        <input type="file" name="excel_file" required accept=".xlsx,.xls,.csv"
                               class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-black file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100 transition-all border border-slate-200 rounded-2xl cursor-pointer">
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-8 py-5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" @click="importOpen = false" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-200/50 rounded-xl transition-all">
                        İptal
                    </button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-black text-sm transition-all shadow-lg shadow-indigo-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        YÜKLE VE AKTAR
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>

@endsection
