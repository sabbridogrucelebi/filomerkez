@extends('layouts.app')

@section('title', 'Yakıt Kayıtları')
@section('subtitle', 'Yakıt operasyon ve istasyon yönetimi')

@section('content')
@php
    use Carbon\Carbon;

    $fuels = collect($fuels ?? []);
    $stationSummaries = collect($stationSummaries ?? []);
    $vehiclesForFilter = $fuels->pluck('vehicle')->filter()->unique('id')->sortBy('plate')->values();

    $fuelTypes = [
        'Dizel' => 'bg-emerald-100 text-emerald-700',
        'Benzin' => 'bg-rose-100 text-rose-700',
        'LPG' => 'bg-violet-100 text-violet-700',
        'Eurodiesel' => 'bg-sky-100 text-sky-700',
        'AdBlue' => 'bg-cyan-100 text-cyan-700',
    ];

    $calculatedRows = collect();

    foreach ($fuels->groupBy('vehicle_id') as $vehicleId => $vehicleRows) {
        $sortedRows = $vehicleRows
            ->sortBy(function ($row) {
                return sprintf(
                    '%s-%010d-%010d',
                    optional($row->date)->format('Ymd') ?? '00000000',
                    (int) ($row->km ?? 0),
                    (int) $row->id
                );
            })
            ->values();

        $previousRow = null;

        foreach ($sortedRows as $row) {
            $row->km_difference = null;
            $row->km_per_liter = null;

            if (
                $previousRow &&
                !is_null($row->km) &&
                !is_null($previousRow->km) &&
                (float) $row->km > (float) $previousRow->km
            ) {
                $kmDifference = (float) $row->km - (float) $previousRow->km;
                $row->km_difference = $kmDifference;

                if ((float) $row->liters > 0) {
                    $row->km_per_liter = $kmDifference / (float) $row->liters;
                }
            }

            $calculatedRows->push($row);
            $previousRow = $row;
        }
    }

    foreach ($calculatedRows as $row) {
        $row->is_paid = $row->payment_status === 'paid';
    }

    $displayRows = $calculatedRows
        ->sortByDesc(function ($row) {
            return sprintf(
                '%s-%010d',
                optional($row->date)->format('Ymd') ?? '00000000',
                (int) $row->id
            );
        })
        ->values();

    $totalLiters = (float) $displayRows->sum(fn ($row) => (float) ($row->liters ?? 0));
    $totalAmount = (float) $displayRows->sum(fn ($row) => (float) ($row->gross_total_cost ?? $row->total_cost ?? 0));
    $totalReceiptCount = (int) $displayRows->count();

    $stationCards = $stationSummaries->values()->map(function ($station) {
        $debt = (float) ($station->current_debt ?? 0);

        return [
            'name' => $station->name ?? '-',
            'debt' => $debt,
            'from' => $debt > 0 ? 'from-rose-500' : 'from-emerald-500',
            'to'   => $debt > 0 ? 'to-pink-500' : 'to-teal-500',
            'status_text' => $debt > 0 ? 'Ödeme Bekliyor' : 'Ödendi',
        ];
    });

    $defaultStationCard = [
        'name' => 'Cari İstasyon Yok',
        'debt' => 0,
        'from' => 'from-emerald-500',
        'to' => 'to-teal-500',
        'status_text' => 'Ödendi',
    ];

    $filterStartDate = request('start_date');
    $filterEndDate = request('end_date');
    $filterVehicleId = request('vehicle_id');
    $filterStation = request('station');
    $filterFuelType = request('fuel_type');
    $filterSearch = request('search');

    $filteredRows = $displayRows->filter(function ($row) use (
        $filterStartDate,
        $filterEndDate,
        $filterVehicleId,
        $filterStation,
        $filterFuelType,
        $filterSearch
    ) {
        $rowDate = optional($row->date)->format('Y-m-d');

        if ($filterStartDate && (!$rowDate || $rowDate < $filterStartDate)) {
            return false;
        }

        if ($filterEndDate && (!$rowDate || $rowDate > $filterEndDate)) {
            return false;
        }

        if ($filterVehicleId && (string) $row->vehicle_id !== (string) $filterVehicleId) {
            return false;
        }

        $rowStation = $row->station?->name ?? $row->station_name ?? '';
        if ($filterStation && mb_strtolower($rowStation) !== mb_strtolower($filterStation)) {
            return false;
        }

        $rowFuelType = $row->fuel_type ?? 'Dizel';
        if ($filterFuelType && $rowFuelType !== $filterFuelType) {
            return false;
        }

        if ($filterSearch) {
            $haystack = mb_strtolower(implode(' ', [
                $row->vehicle?->plate ?? '',
                $row->vehicle?->brand ?? '',
                $row->vehicle?->model ?? '',
                $rowStation,
                $row->notes ?? '',
            ]));

            if (!str_contains($haystack, mb_strtolower($filterSearch))) {
                return false;
            }
        }

        return true;
    })->values();

    $stationOptions = $displayRows
        ->map(fn ($row) => $row->station?->name ?? $row->station_name)
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $fuelTypeOptions = $displayRows
        ->map(fn ($row) => $row->fuel_type ?? 'Dizel')
        ->filter()
        ->unique()
        ->sort()
        ->values();

    $perPage = 100;
    $currentPage = max((int) request('page', 1), 1);
    $totalFiltered = $filteredRows->count();
    $lastPage = max((int) ceil($totalFiltered / $perPage), 1);
    $currentPage = min($currentPage, $lastPage);

    $paginatedRows = $filteredRows
        ->slice(($currentPage - 1) * $perPage, $perPage)
        ->values();

    $queryWithoutPage = request()->except('page');
@endphp

<div
    class="space-y-6"
    x-data='{
        stationCards: @json($stationCards->count() ? $stationCards : collect([$defaultStationCard])),
        currentStationIndex: 0,
        showImportModal: false,
        init() {
            if (this.stationCards.length > 1) {
                setInterval(() => {
                    this.currentStationIndex = (this.currentStationIndex + 1) % this.stationCards.length;
                }, 5000);
            }
        },
        currentStationCard() {
            return this.stationCards[this.currentStationIndex] ?? {
                name: "Cari İstasyon Yok",
                debt: 0,
                from: "from-emerald-500",
                to: "to-teal-500",
                status_text: "Ödendi"
            };
        },
        stationCardClass() {
            const card = this.currentStationCard();
            return `${card.from} ${card.to}`;
        }
    }'
>
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm">
            <div class="mb-2 font-semibold">Lütfen aşağıdaki hataları düzeltin:</div>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-[32px] border border-slate-200/70 bg-white/95 shadow-[0_20px_60px_rgba(15,23,42,0.08)] overflow-hidden">
        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-blue-50/40 px-6 py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center rounded-[20px] shadow-sm">
                        <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Fuel%20Pump.png" alt="Yakıt" class="w-16 h-16 drop-shadow-xl" />
                    </div>

                    <div>
                        <h2 class="text-[26px] font-bold tracking-tight text-slate-900">Yakıt Yönetimi</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Yakıt kayıtları, km tüketim analizi, istasyon takibi ve borç raporlaması
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ Route::has('fuel-stations.index') ? route('fuel-stations.index') : '#' }}"
                       class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 {{ Route::has('fuel-stations.index') ? '' : 'pointer-events-none opacity-60' }}">
                        Petrol İstasyonları
                    </a>

                    @if(auth()->user()->hasPermission('fuels.edit'))
                    <a href="{{ route('fuels.consumption-settings') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition-all hover:bg-slate-50 hover:shadow-md hover:-translate-y-0.5 group">
                        <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Gear.png" alt="Ayarlar" class="w-5 h-5 drop-shadow-md transition-transform duration-700 group-hover:rotate-180" />
                        Yakıt Oran Belirleme
                    </a>
                    @endif

                    @if(auth()->user()->hasPermission('fuels.create'))
                    <button type="button"
                            @click="showImportModal = true"
                            class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-3 rounded-2xl font-black text-sm transition-all shadow-sm hover:-translate-y-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        TOPLU EKLE
                    </button>
                    @endif

                    <a href="{{ route('reports.fuels.csv', request()->query()) }}"
                       class="inline-flex items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                        Excel İndir
                    </a>

                    @if(auth()->user()->hasPermission('fuels.create'))
                    <a href="{{ route('fuels.create') }}"
                       class="inline-flex items-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200/60 transition hover:scale-[1.01]">
                        + Yeni Yakıt Kaydı Ekle
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[26px] relative overflow-hidden bg-gradient-to-br from-sky-500 to-blue-600 p-5 text-white shadow-xl flex items-center justify-between">
                    <div class="relative z-10">
                        <div class="text-sm font-medium text-white/90">Toplam Yakıt Tutarı</div>
                        <div class="mt-3 text-2xl xl:text-3xl font-extrabold tracking-tight truncate">{{ number_format($totalAmount, 2, ',', '.') }} ₺</div>
                        <div class="mt-2 text-xs text-white/80">Kayıtlı toplam yakıt maliyeti</div>
                    </div>
                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Money%20Bag.png" alt="Tutar" class="relative z-10 w-16 h-16 drop-shadow-xl select-none pointer-events-none flex-shrink-0 ml-2" />
                </div>

                <div class="rounded-[26px] relative overflow-hidden bg-gradient-to-br from-emerald-500 to-teal-500 p-5 text-white shadow-xl flex items-center justify-between">
                    <div class="relative z-10">
                        <div class="text-sm font-medium text-white/90">Toplam Litre</div>
                        <div class="mt-3 text-2xl xl:text-3xl font-extrabold tracking-tight truncate">{{ number_format($totalLiters, 2, ',', '.') }}</div>
                        <div class="mt-2 text-xs text-white/80">Sisteme işlenen toplam litre</div>
                    </div>
                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Rocket.png" alt="Litre" class="relative z-10 w-16 h-16 drop-shadow-xl select-none pointer-events-none flex-shrink-0 ml-2" />
                </div>

                <div class="rounded-[26px] relative overflow-hidden bg-gradient-to-br from-indigo-500 to-blue-600 p-5 text-white shadow-xl flex items-center justify-between">
                    <div class="relative z-10">
                        <div class="text-sm font-medium text-white/90">Yakıt Fiş Adedi</div>
                        <div class="mt-3 text-2xl xl:text-3xl font-extrabold tracking-tight truncate">{{ number_format($totalReceiptCount, 0, ',', '.') }}</div>
                        <div class="mt-2 text-xs text-white/80">Toplam kayıtlı yakıt fişi sayısı</div>
                    </div>
                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Receipt.png" alt="Fiş" class="relative z-10 w-14 h-14 drop-shadow-xl select-none pointer-events-none flex-shrink-0 ml-2" />
                </div>

                <div class="rounded-[26px] p-5 text-white shadow-xl transition-all duration-500 bg-gradient-to-br relative overflow-hidden" :class="stationCardClass()">
                    <div class="absolute right-4 top-4 rounded-full bg-white/20 px-2.5 py-1 text-[10px] font-bold text-white tracking-wider uppercase">
                        5 sn
                    </div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div>
                            <div>
                                <div class="text-sm font-medium text-white/90">İstasyon Cari Borcu</div>
                                <div class="mt-1 text-xs text-white/80 line-clamp-1 pr-12" x-text="currentStationCard().name"></div>
                            </div>

                            <div class="mt-3 text-2xl xl:text-3xl font-extrabold tracking-tight truncate">
                                <span x-text="new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(currentStationCard().debt)"></span> ₺
                            </div>

                            <div class="mt-3 inline-flex rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white" x-text="currentStationCard().status_text"></div>
                        </div>
                        <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Fuel%20Pump.png" alt="İstasyon" class="w-16 h-16 drop-shadow-xl select-none pointer-events-none flex-shrink-0 ml-2 mt-4" />
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200/70 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-violet-50/30 px-6 py-5">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Yakıt Kayıt Listesi</h3>
                            <p class="mt-1 text-sm text-slate-500">Araç bazlı km, litre, tüketim ve maliyet görünümü</p>
                        </div>

                        <form method="GET" action="{{ route('fuels.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6 w-full xl:w-auto">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Başlangıç</label>
                                <input type="date"
                                       name="start_date"
                                       value="{{ $filterStartDate }}"
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Bitiş</label>
                                <input type="date"
                                       name="end_date"
                                       value="{{ $filterEndDate }}"
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Araç</label>
                                <select name="vehicle_id"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                                    <option value="">Tümü</option>
                                    @foreach($vehiclesForFilter as $vehicle)
                                        <option value="{{ $vehicle->id }}" {{ (string) $filterVehicleId === (string) $vehicle->id ? 'selected' : '' }}>
                                            {{ $vehicle->plate }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">İstasyon</label>
                                <select name="station"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                                    <option value="">Tümü</option>
                                    @foreach($stationOptions as $stationOption)
                                        <option value="{{ $stationOption }}" {{ $filterStation === $stationOption ? 'selected' : '' }}>
                                            {{ $stationOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Yakıt Türü</label>
                                <select name="fuel_type"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                                    <option value="">Tümü</option>
                                    @foreach($fuelTypeOptions as $fuelTypeOption)
                                        <option value="{{ $fuelTypeOption }}" {{ $filterFuelType === $fuelTypeOption ? 'selected' : '' }}>
                                            {{ $fuelTypeOption }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Arama</label>
                                <input type="text"
                                       name="search"
                                       value="{{ $filterSearch }}"
                                       placeholder="Plaka / not / istasyon"
                                       class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                            </div>

                            <div class="xl:col-span-6 flex flex-wrap items-center justify-end gap-3">
                                <a href="{{ route('fuels.index') }}"
                                   class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Temizle
                                </a>

                                <button type="submit"
                                        class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200/60 transition hover:scale-[1.01]">
                                    Filtrele
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1450px] text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Tarih</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Araç</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İstasyon</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Yakıt Türü</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">KM</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">KM Farkı</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Litre</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Birim Fiyat</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Toplam</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">KM / Litre</th>
                                <th class="px-4 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Not</th>
                                <th class="px-4 py-4 text-right text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($paginatedRows as $fuel)
                                @php
                                    $fuelType = $fuel->fuel_type ?? 'Dizel';
                                    $fuelTypeClass = $fuelTypes[$fuelType] ?? 'bg-slate-100 text-slate-700';
                                @endphp

                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-4 text-slate-700">
                                        {{ Carbon::parse($fuel->date)->format('d.m.Y') }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-800">{{ $fuel->vehicle?->plate ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">{{ $fuel->vehicle?->brand ?? '' }} {{ $fuel->vehicle?->model ?? '' }}</div>
                                    </td>

                                    <td class="px-4 py-4 text-slate-600">
                                        {{ $fuel->station?->name ?? $fuel->station_name ?? '-' }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $fuelTypeClass }}">
                                            {{ $fuelType }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ !is_null($fuel->km) ? number_format((float) $fuel->km, 0, ',', '.') : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ !is_null($fuel->km_difference) ? number_format((float) $fuel->km_difference, 0, ',', '.') . ' KM' : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ number_format((float) ($fuel->liters ?? 0), 2, ',', '.') }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ number_format((float) ($fuel->price_per_liter ?? 0), 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-4 py-4 font-semibold {{ $fuel->is_paid ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ number_format((float) ($fuel->gross_total_cost ?? $fuel->total_cost ?? 0), 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-4 py-4 text-slate-700">
                                        {{ !is_null($fuel->km_per_liter) ? number_format((float) $fuel->km_per_liter, 2, ',', '.') : '-' }}
                                    </td>

                                    <td class="px-4 py-4 text-slate-500 max-w-[220px] truncate">
                                        {{ $fuel->notes ?: '-' }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            @if(auth()->user()->hasPermission('fuels.edit'))
                                            <a href="{{ route('fuels.edit', $fuel) }}"
                                               class="rounded-xl bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                                Düzenle
                                            </a>
                                            @endif

                                            @if(auth()->user()->hasPermission('fuels.delete'))
                                            <form action="{{ route('fuels.destroy', $fuel) }}" method="POST" class="inline" onsubmit="return confirm('Bu yakıt kaydını silmek istediğine emin misin?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                                    Sil
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="px-4 py-10 text-center text-slate-500">
                                        Filtreye uygun yakıt kaydı bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($lastPage > 1)
                    <div class="border-t border-slate-100 bg-white px-6 py-4">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="text-sm text-slate-500">
                                Toplam <span class="font-semibold text-slate-700">{{ number_format($totalFiltered, 0, ',', '.') }}</span> kayıt,
                                sayfa <span class="font-semibold text-slate-700">{{ $currentPage }}</span> / <span class="font-semibold text-slate-700">{{ $lastPage }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @php
                                    $prevPage = max($currentPage - 1, 1);
                                    $nextPage = min($currentPage + 1, $lastPage);
                                @endphp

                                <a href="{{ route('fuels.index', array_merge($queryWithoutPage, ['page' => 1])) }}"
                                   class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 {{ $currentPage === 1 ? 'pointer-events-none opacity-40' : '' }}">
                                    İlk
                                </a>

                                <a href="{{ route('fuels.index', array_merge($queryWithoutPage, ['page' => $prevPage])) }}"
                                   class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 {{ $currentPage === 1 ? 'pointer-events-none opacity-40' : '' }}">
                                    Önceki
                                </a>

                                @for($page = max(1, $currentPage - 2); $page <= min($lastPage, $currentPage + 2); $page++)
                                    <a href="{{ route('fuels.index', array_merge($queryWithoutPage, ['page' => $page])) }}"
                                       class="rounded-xl px-3 py-2 text-sm font-semibold transition {{ $page === $currentPage ? 'bg-gradient-to-r from-indigo-600 to-blue-600 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                        {{ $page }}
                                    </a>
                                @endfor

                                <a href="{{ route('fuels.index', array_merge($queryWithoutPage, ['page' => $nextPage])) }}"
                                   class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 {{ $currentPage === $lastPage ? 'pointer-events-none opacity-40' : '' }}">
                                    Sonraki
                                </a>

                                <a href="{{ route('fuels.index', array_merge($queryWithoutPage, ['page' => $lastPage])) }}"
                                   class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 {{ $currentPage === $lastPage ? 'pointer-events-none opacity-40' : '' }}">
                                    Son
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Toplu Yakıt Ekle Modal --}}
    @if(auth()->user()->hasPermission('fuels.create'))
    <div x-show="showImportModal" 
         x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-0">
        
        <div x-show="showImportModal" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
             @click="showImportModal = false"></div>

        <div x-show="showImportModal" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative bg-white rounded-[32px] shadow-2xl overflow-hidden w-full max-w-xl transform transition-all border border-slate-100 flex flex-col max-h-[90vh]">
            
            {{-- Header --}}
            <div class="px-8 py-6 bg-slate-50 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-xl font-black text-slate-800">Toplu Yakıt Ekle</h3>
                    <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">Excel Dosyası ile İçe Aktar</p>
                </div>
                <button @click="showImportModal = false" class="text-slate-400 hover:text-rose-500 transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Form --}}
            <form action="{{ route('fuels.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0">
                @csrf
                <div class="p-8 space-y-6 overflow-y-auto">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-5">
                        <div class="flex items-start gap-4">
                            <div class="h-10 w-10 shrink-0 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-indigo-900 mb-1">Örnek Şablonu İndirin</h4>
                                <p class="text-[11px] font-bold text-indigo-700/70 mb-3 leading-relaxed">
                                    Yakıt kayıtlarını sisteme sorunsuz aktarmak için öncelikle örnek Excel şablonunu indirin ve doğru formatta doldurun.
                                </p>
                                <a href="{{ route('fuels.import.template') }}" class="inline-flex items-center gap-1.5 text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-widest bg-white px-3 py-1.5 rounded-lg border border-indigo-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    ŞABLONU İNDİR
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Excel Sütun Rehberi --}}
                    <div>
                        <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3">Sütun Doldurma Rehberi</h4>
                        <div class="grid grid-cols-2 gap-3 text-[10px] font-medium text-slate-600">
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">A) plaka <span class="text-rose-500">*</span></span>
                                Aracın plakası (Örn: 34ABC123)
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">B) yakit_turu</span>
                                Dizel, Benzin vb. (Boş: Dizel)
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">C) istasyon</span>
                                Örn: Shell Ataşehir
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">D) tarih <span class="text-rose-500">*</span></span>
                                Alım tarihi (Örn: 15.05.2026)
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">E) litre</span>
                                Alınan yakıt miktarı
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">F) litre_fiyati</span>
                                1 litre fiyatı (İndirimsiz)
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">G) km</span>
                                O anki kilometre
                            </div>
                            <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                                <span class="font-black text-slate-800 block mb-0.5">H) notlar</span>
                                Ek bilgiler (Örn: Nakit alındı)
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
                <div class="px-8 py-5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" @click="showImportModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-200/50 rounded-xl transition-all">
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

<style>
    [x-cloak] {
        display: none !important;
    }
</style>
@endsection
