@extends(request('is_customer_portal') ? 'layouts.customer-portal' : 'layouts.app')
@section('title', 'Puantaj / Seferler')
@section('subtitle', request('is_customer_portal') ? 'Aylık servis hakediş raporu' : 'Müşteri bazlı aylık sefer puantaj kayıtları')

@section('content')
<style>
    #print-only-table { display: none; }
</style>
<div x-data="puantajMatrix()" class="space-y-6 pb-24 screen-only">

    {{-- ÜST BİLGİ VE FİLTRELEME --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between mb-4">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $selectedCustomer ? $selectedCustomer->company_name . ' Puantajı' : 'Puantaj / Seferler' }}</h2>
            <p class="text-sm font-medium text-slate-500">Müşterilerin günlük servis kayıtlarını excel formatında hızlıca doldurun.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 no-print">
            <button onclick="printPuantaj()" type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <span>🖨️ PDF / Yazdır</span>
            </button>
            <button type="button" onclick="document.getElementById('export-input').value='excel'; document.getElementById('filter-form').submit(); document.getElementById('export-input').value='';" class="inline-flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                <span>📊 Excel İndir</span>
            </button>
        </div>
    </div>

    {{-- FİLTRE FORMU --}}
    <form id="filter-form" method="GET" action="{{ request('is_customer_portal') ? route('customer.portal.trips') : route('trips.index') }}" class="rounded-[30px] border border-slate-200/60 bg-white/90 p-5 shadow-xl backdrop-blur flex flex-wrap gap-4 items-end no-print">
        <input type="hidden" name="export" id="export-input" value="">
        <input type="hidden" name="hidden_routes" :value="hiddenRoutes.join(',')">
        @if(!request('is_customer_portal'))
        <div class="flex-1 min-w-[250px]">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Müşteri Seçin</label>
            <select name="customer_id" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                <option value="">-- Müşteri Seçiniz --</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ $selectedCustomerId == $customer->id ? 'selected' : '' }}>
                        {{ $customer->company_name }}
                    </option>
                @endforeach
            </select>
        </div>
        @else
        <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
        <input type="hidden" name="is_customer_portal" value="1">
        @endif

        <div class="w-32">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Ay</label>
            <select name="month" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                @foreach($monthOptions as $mNum => $mName)
                    <option value="{{ $mNum }}" {{ $selectedMonth == $mNum ? 'selected' : '' }}>{{ $mName }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-32">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Yıl</label>
            <select name="year" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                @foreach($yearOptions as $y)
                    <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @if(!$selectedCustomer)
        {{-- BOŞ DURUM --}}
        <div class="flex flex-col items-center justify-center rounded-[32px] border border-slate-200/60 bg-white/50 py-24 shadow-sm backdrop-blur no-print">
            <div class="mb-6 flex items-center justify-center">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Office%20Building.png" alt="Müşteri Seçilmedi" class="w-32 h-32  drop-shadow-2xl" />
            </div>
            <h3 class="text-xl font-bold text-slate-900">Müşteri Seçilmedi</h3>
            <p class="mt-2 max-w-sm text-center text-sm text-slate-500">Puantaj tablosunu görüntülemek ve veri girişi yapmak için lütfen yukarıdan bir müşteri seçiniz.</p>
        </div>
    @else
        <div id="print-area">
            {{-- MATRİS TABLOSU --}}
            <div class="overflow-hidden rounded-[30px] border border-slate-200/60 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 bg-gradient-to-r from-slate-50 to-white">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">{{ $selectedCustomer->company_name }} - {{ $monthOptions[$selectedMonth] }} {{ $selectedYear }} Puantajı</h3>
                        @if(!request('is_customer_portal'))
                        <p class="mt-1 text-sm text-slate-500 no-print">Hücrelere tıklayarak o günkü sefer araçlarını değiştirebilirsiniz.</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <!-- Filtre Menüsü -->
                        <div class="relative" x-data="{ openFilter: false }">
                            <button @click="openFilter = !openFilter" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" :class="hiddenRoutes.length > 0 ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : ''">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                <span>Filtrele</span>
                                <span x-show="hiddenRoutes.length > 0" class="ml-1 inline-flex items-center justify-center bg-indigo-600 text-white rounded-full h-5 w-5 text-[10px]" x-text="hiddenRoutes.length"></span>
                            </button>

                            <div x-show="openFilter" @click.away="openFilter = false" class="absolute right-0 mt-2 w-72 origin-top-right rounded-2xl border border-slate-100 bg-white p-2 shadow-xl z-50" style="display: none;" x-transition>
                                <div class="px-3 py-2 text-xs font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 mb-2">Gizlenecek Güzergahlar</div>
                                <div class="max-h-60 overflow-y-auto">
                                    @foreach($serviceRoutes as $route)
                                    <label class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer transition">
                                        <input type="checkbox" value="{{ $route->id }}" x-model="hiddenRoutes" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                                        <span class="text-sm font-medium text-slate-700 truncate" title="{{ $route->route_name }}">{{ $route->route_name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                <div class="mt-2 pt-2 border-t border-slate-100">
                                    <button @click="hiddenRoutes = []; openFilter = false" type="button" class="w-full rounded-lg px-3 py-2 text-center text-xs font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">Filtreyi Temizle</button>
                                </div>
                            </div>
                        </div>

                        <!-- Toplam Güzergah -->
                        <div class="text-sm font-bold text-slate-600 bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-100 flex items-center gap-1">
                            Toplam Güzergah: <span x-text="totalRoutes - hiddenRoutes.length">{{ $serviceRoutes->count() }}</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-auto max-h-[65vh] matrix-container relative shadow-inner rounded-xl border border-slate-200">
                    <table id="puantaj-table" class="w-full text-left text-sm border-separate border-spacing-0">
                    <thead class="shadow-sm relative z-40">
                        <tr>
                            <th class="sticky left-0 top-0 z-50 w-64 min-w-[250px] bg-slate-100 px-4 py-4 font-bold uppercase tracking-[0.1em] text-slate-600 border-r border-b border-slate-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                                Güzergah
                            </th>
                            @foreach($monthDays as $day)
                                @php
                                    $thClasses = 'bg-slate-50 text-slate-600';
                                    if ($day['is_holiday']) {
                                        $thClasses = 'bg-fuchsia-100 text-fuchsia-800';
                                    } elseif ($day['is_weekend']) {
                                        $thClasses = 'bg-rose-50/50 text-rose-700';
                                    }
                                @endphp
                                <th id="day-header-{{ $day['date']->format('d') }}" class="sticky top-0 z-30 min-w-[120px] border-b border-r border-slate-100 px-2 py-3 text-center transition-colors {{ $thClasses }}" title="{{ $day['holiday_name'] ?? '' }}">
                                    <div class="font-black text-lg">{{ $day['date']->format('d') }}</div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider opacity-70">{{ $day['day_name'] }}</div>
                                    @if($day['is_holiday'])
                                        <div class="text-[9px] font-bold uppercase text-fuchsia-600 truncate mt-0.5 px-1">{{ $day['holiday_name'] }}</div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($serviceRoutes as $route)
                            @php
                                $dMName = ''; $dEName = '';
                                if ($route->morningVehicle && $route->morningVehicle->drivers->isNotEmpty()) {
                                    $dM = $route->morningVehicle->drivers->where('is_active', true)->first() ?? $route->morningVehicle->drivers->first();
                                    $dMName = trim($dM->full_name);
                                }
                                if ($route->eveningVehicle && $route->eveningVehicle->drivers->isNotEmpty()) {
                                    $dE = $route->eveningVehicle->drivers->where('is_active', true)->first() ?? $route->eveningVehicle->drivers->first();
                                    $dEName = trim($dE->full_name);
                                }
                            @endphp
                            @php
                                $routeBgClass = 'bg-white';
                                if ($route->service_type === 'morning') $routeBgClass = 'bg-amber-50';
                                elseif ($route->service_type === 'evening') $routeBgClass = 'bg-emerald-50';
                                elseif ($route->service_type === 'shift') $routeBgClass = 'bg-orange-100';
                            @endphp
                            <tr class="group transition-colors" x-show="!hiddenRoutes.includes('{{ $route->id }}')">
                                <td class="sticky left-0 z-20 {{ $routeBgClass }} px-4 py-3 border-r border-b border-slate-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] group-hover:brightness-[0.97] transition-all">
                                    <div class="font-bold text-slate-900 truncate">{{ $route->route_name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 flex flex-col gap-1">
                                        @if($route->service_type !== 'evening')
                                        <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-400"></span> Sabah: {{ $route->morningVehicle->plate ?? 'Tanımsız' }}</div>
                                        @endif
                                        @if($route->service_type !== 'morning')
                                        <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-400"></span> Akşam: {{ $route->eveningVehicle->plate ?? 'Tanımsız' }}</div>
                                        @endif
                                    </div>
                                </td>
                                @foreach($monthDays as $day)
                                    @php
                                        $cell = $matrix[$day['date_key']][$route->id] ?? null;
                                        $hasRecord = $cell['has_record'] ?? false;
                                        $price = $cell['value'] ?? '';
                                        $mPlate = $cell['morning_vehicle_plate'] ?? $cell['default_morning_vehicle_plate'] ?? 'Tanımsız';
                                        $ePlate = $cell['evening_vehicle_plate'] ?? $cell['default_evening_vehicle_plate'] ?? 'Tanımsız';
                                        
                                        $mDriver = $cell['morning_driver_name'] ?? $cell['default_morning_driver_name'] ?? '';
                                        $eDriver = $cell['evening_driver_name'] ?? $cell['default_evening_driver_name'] ?? '';

                                        $isMorningDiff = $hasRecord && ($cell['morning_vehicle_id'] !== $cell['default_morning_vehicle_id']);
                                        $isEveningDiff = $hasRecord && ($cell['evening_vehicle_id'] !== $cell['default_evening_vehicle_id']);

                                        $cellData = json_encode([
                                            'route_id' => $route->id,
                                            'route_name' => $route->route_name,
                                            'service_type' => $route->service_type,
                                            'date' => $day['date_key'],
                                            'display_date' => $day['display_date'],
                                            'has_record' => $hasRecord,
                                            'price' => $price,
                                            'morning_id' => $cell['morning_vehicle_id'] ?? $cell['default_morning_vehicle_id'] ?? '',
                                            'evening_id' => $cell['evening_vehicle_id'] ?? $cell['default_evening_vehicle_id'] ?? '',
                                            'default_morning_id' => $cell['default_morning_vehicle_id'] ?? '',
                                            'default_evening_id' => $cell['default_evening_vehicle_id'] ?? '',
                                            'morning_driver_id' => $cell['morning_driver_id'] ?? '',
                                            'evening_driver_id' => $cell['evening_driver_id'] ?? '',
                                            'default_morning_plate' => ($cell['default_morning_vehicle_plate'] ?? 'Tanımsız') . ($mDriver ? ' ('.$mDriver.')' : ''),
                                            'default_evening_plate' => ($cell['default_evening_vehicle_plate'] ?? 'Tanımsız') . ($eDriver ? ' ('.$eDriver.')' : ''),
                                            'status' => $cell['trip_status'] ?? 'Yapıldı',
                                        ]);
                                        
                                        $tdClasses = '';
                                        if ($day['is_holiday']) {
                                            $tdClasses = 'bg-fuchsia-50/20';
                                        } elseif ($day['is_weekend']) {
                                            $tdClasses = 'bg-rose-50/30';
                                        }
                                    @endphp
                                    <td class="border-b border-r border-slate-100 p-1.5 relative {{ $tdClasses }} hover:bg-indigo-50/50 transition-colors group/cell" data-date="{{ $day['date']->format('d') }}" data-has-record="{{ $hasRecord ? 'true' : 'false' }}">
                                        <div class="flex flex-col h-full gap-1">
                                            <!-- Fiyat Girişi -->
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">₺</span>
                                                <input type="number" 
                                                       step="0.01"
                                                       class="w-full h-8 pl-5 pr-2 rounded-lg border text-sm font-bold text-slate-800 transition-all focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 {{ $hasRecord ? 'border-indigo-200 bg-white shadow-inner' : 'border-slate-200 bg-slate-50/50 group-hover/cell:bg-white' }} {{ request('is_customer_portal') ? 'cursor-not-allowed opacity-80 pointer-events-none' : '' }}"
                                                       placeholder="Tutar"
                                                       value="{{ $price }}"
                                                       {{ request('is_customer_portal') ? 'readonly tabindex="-1"' : '' }}
                                                       @if(!request('is_customer_portal'))
                                                       @keydown.enter.prevent="startPriceEntry({{ $cellData }}, $event.target.value)"
                                                       @endif
                                                       >
                                                <span class="print-value hidden">{{ $price ? '₺'.$price : '' }}</span>
                                            </div>
                                            
                                            <!-- Araç ve Şoför Gösterimi (Sadece kayıt varsa göster) -->
                                            @if($hasRecord)
                                            <div class="flex flex-col gap-0.5 mt-1">
                                                @if($route->service_type !== 'evening')
                                                @php
                                                    $isMCustom = false;
                                                    $mDisplayDriver = $cell['morning_driver_name'] ?? $cell['default_morning_driver_name'] ?? 'Tanımsız';
                                                    if (!empty($cell['morning_manual_driver_name'])) {
                                                        $mDisplayDriver = $cell['morning_manual_driver_name'];
                                                        if (!empty($cell['default_morning_driver_id']) && $cell['morning_driver_id'] != $cell['default_morning_driver_id']) {
                                                            $isMCustom = true;
                                                        }
                                                    }
                                                @endphp
                                                <div class="text-[9px] font-bold px-1.5 py-1 rounded flex flex-col gap-0.5 {{ $isMorningDiff ? 'bg-emerald-200 text-emerald-900 border-2 border-dashed border-emerald-500' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}" title="{{ $isMorningDiff ? 'Farklı Araç Gitti!' : 'Sabah Aracı' }}">
                                                    <div class="flex items-center justify-between">
                                                        <span>S:</span><span class="truncate ml-1">{{ $mPlate }}</span>
                                                    </div>
                                                </div>
                                                @endif

                                                @if($route->service_type !== 'morning')
                                                @php
                                                    $isECustom = false;
                                                    $eDisplayDriver = $cell['evening_driver_name'] ?? $cell['default_evening_driver_name'] ?? 'Tanımsız';
                                                    if (!empty($cell['evening_manual_driver_name'])) {
                                                        $eDisplayDriver = $cell['evening_manual_driver_name'];
                                                        if (!empty($cell['default_evening_driver_id']) && $cell['evening_driver_id'] != $cell['default_evening_driver_id']) {
                                                            $isECustom = true;
                                                        }
                                                    }
                                                @endphp
                                                <div class="text-[9px] font-bold px-1.5 py-1 rounded flex flex-col gap-0.5 {{ $isEveningDiff ? 'bg-rose-200 text-rose-900 border-2 border-dashed border-rose-500' : 'bg-rose-100 text-rose-800 border border-rose-200' }}" title="{{ $isEveningDiff ? 'Farklı Araç Gitti!' : 'Akşam Aracı' }}">
                                                    <div class="flex items-center justify-between">
                                                        <span>A:</span><span class="truncate ml-1">{{ $ePlate }}</span>
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                            @endif

                                            @if($hasRecord)
                                                <div class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full shadow-sm"></div>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ÖZET --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Ara Toplam</div>
                <div class="mt-2 text-3xl font-black text-slate-900">₺{{ number_format($summary['subtotal'], 2, ',', '.') }}</div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-sm font-bold text-slate-500">KDV (%{{ $summary['vat_rate'] }})</div>
                <div class="mt-2 text-3xl font-black text-slate-900">₺{{ number_format($summary['vat_amount'], 2, ',', '.') }}</div>
            </div>
            <div class="rounded-3xl border border-transparent bg-gradient-to-br from-slate-800 to-slate-900 p-6 shadow-xl text-white">
                <div class="text-sm font-bold text-slate-300">Net Fatura Tutarı</div>
                <div class="mt-2 text-3xl font-black text-white">₺{{ number_format($summary['net_total'], 2, ',', '.') }}</div>
            </div>
        </div>

        @if($summary['withholding_amount'] > 0)
        {{-- TEVKİFAT KARTI --}}
        <div class="mt-5 rounded-3xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Objects/Scissors.png" alt="Tevkifat" class="w-16 h-16 drop-shadow-xl" />
                <div>
                    <div class="text-sm font-bold text-amber-800">Tevkifat Tutarı (%{{ $summary['withholding_rate'] }})</div>
                    <p class="text-xs font-medium text-amber-600 mt-0.5">Bu tutar faturada KDV'den düşülecektir.</p>
                </div>
            </div>
            <div class="text-3xl font-black text-amber-900">₺{{ number_format($summary['withholding_amount'], 2, ',', '.') }}</div>
        </div>
        @endif
    
    @if(!request('is_customer_portal'))
    {{-- ONAY MODALI --}}
    <div x-show="isConfirmModalOpen" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm" style="display:none;">
        <div @click.away="closeModals()" class="w-full max-w-md overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-2xl">
            <div class="p-6 text-center">
                <div class="mx-auto mb-4 flex items-center justify-center">
                    <img src="https://raw.githubusercontent.com/Tarikul-Islam-Anik/Animated-Fluent-Emojis/master/Emojis/Travel%20and%20places/Bus.png" alt="Sefer" class="w-20 h-20 drop-shadow-2xl" />
                </div>
                <h3 class="text-xl font-black text-slate-900" x-text="activeCell.display_date + ' Seferi'"></h3>
                <p class="mt-2 text-sm text-slate-500 font-medium">Bu seferi <b>varsayılan/tanımlı araçlar</b> mı yaptı?</p>
                
                <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col gap-2 text-sm text-left">
                    <div class="flex items-center justify-between" x-show="activeCell.service_type !== 'evening'">
                        <span class="text-slate-500">Sabah Aracı:</span>
                        <span class="font-bold text-slate-800" x-text="activeCell.default_morning_plate"></span>
                    </div>
                    <div class="flex items-center justify-between" x-show="activeCell.service_type !== 'morning'">
                        <span class="text-slate-500">Akşam Aracı:</span>
                        <span class="font-bold text-slate-800" x-text="activeCell.default_evening_plate"></span>
                    </div>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-200">
                        <span class="text-slate-500">Girilen Tutar:</span>
                        <span class="font-black text-emerald-600 text-lg" x-text="'₺' + enteredPrice"></span>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 p-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="openVehicleSelectModal()" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-100">Hayır, Farklı Araç Gitti</button>
                <button type="button" @click="saveQuick()" class="flex-1 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-700">Evet, Tanımlı Araç Yaptı</button>
            </div>
        </div>
    </div>

    {{-- STEP 2: ARAÇ SEÇİM MODALI --}}
    <div x-show="isVehicleModalOpen" x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm" style="display:none;">
        <div @click.away="closeModals()" class="w-full max-w-lg overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 bg-slate-50">
                <div>
                    <h3 class="text-xl font-black text-slate-900" x-text="activeCell.display_date + ' Farklı Araç Seçimi'"></h3>
                    <p class="mt-1 text-sm font-medium text-slate-500" x-text="activeCell.route_name"></p>
                </div>
                <button type="button" @click="closeModals()" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm border border-slate-200 text-slate-500 hover:bg-slate-100 transition">✕</button>
            </div>
            
            <form @submit.prevent="saveWithVehicles" class="p-6 space-y-5">
                <div class="p-4 bg-sky-50 rounded-2xl border border-sky-100" x-show="activeCell.service_type !== 'evening'">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.1em] text-sky-800">☀️ Sabah Aracı (Gidiş)</label>
                    <select x-model="formData.morning_id" class="w-full rounded-xl border border-sky-200 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-500">
                        <option value="">-- Araç Seçilmedi --</option>
                        @foreach($vehicles as $vehicle)
                            @php
                                $vDName = '';
                                if ($vehicle->drivers->isNotEmpty()) {
                                    $dM = $vehicle->drivers->where('is_active', true)->first() ?? $vehicle->drivers->first();
                                    $parts = explode(' ', trim($dM->full_name));
                                    $vDName = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) . ' ' . mb_substr(end($parts), 0, 1) . '.' : $parts[0];
                                }
                            @endphp
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} {{ $vDName ? '('.$vDName.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100" x-show="activeCell.service_type !== 'morning'">
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.1em] text-indigo-800">🌙 Akşam Aracı (Dönüş)</label>
                    <select x-model="formData.evening_id" class="w-full rounded-xl border border-indigo-200 px-4 py-3 text-sm font-bold text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Araç Seçilmedi --</option>
                        @foreach($vehicles as $vehicle)
                            @php
                                $vDName = '';
                                if ($vehicle->drivers->isNotEmpty()) {
                                    $dM = $vehicle->drivers->where('is_active', true)->first() ?? $vehicle->drivers->first();
                                    $parts = explode(' ', trim($dM->full_name));
                                    $vDName = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) . ' ' . mb_substr(end($parts), 0, 1) . '.' : $parts[0];
                                }
                            @endphp
                            <option value="{{ $vehicle->id }}">{{ $vehicle->plate }} {{ $vDName ? '('.$vDName.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-2 text-center pt-2">
                    <button type="button" @click="showDriverSelect = !showDriverSelect" class="inline-flex items-center gap-2 rounded-xl border-b-4 border-slate-300 bg-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition-all hover:translate-y-[2px] hover:border-b-2 hover:bg-slate-300 active:translate-y-[4px] active:border-b-0">
                        <span x-text="showDriverSelect ? '👤 Şoför Seçimini İptal Et' : '👤 Farklı Şoför Gitti'"></span>
                    </button>
                </div>

                <div x-show="showDriverSelect" x-collapse>
                    <div class="p-4 bg-slate-800 rounded-2xl border border-slate-700 shadow-inner flex flex-col gap-4">
                        <label class="block text-xs font-bold uppercase tracking-[0.1em] text-slate-300 border-b border-slate-700 pb-2">👤 Görevli Şoförü Seçin</label>
                        
                        <div x-show="activeCell.service_type !== 'evening'" class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-bold text-sky-400">Sabah Aracı İçin Farklı Şoför (Gidiş)</label>
                            <select x-model="formData.morning_driver_id" class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm focus:border-sky-400 focus:ring-2 focus:ring-sky-500">
                                <option value="">-- Araca Kayıtlı Asıl Şoför Gitti --</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="activeCell.service_type !== 'morning'" class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-bold text-indigo-400">Akşam Aracı İçin Farklı Şoför (Dönüş)</label>
                            <select x-model="formData.evening_driver_id" class="w-full rounded-xl border border-slate-600 bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                                <option value="">-- Araca Kayıtlı Asıl Şoför Gitti --</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p class="text-[10px] text-slate-400">Not: Eğer aracın asıl şoförü rahatsızlanıp yerine başka biri gittiyse ilgili alandan yedek şoförü seçin. Bu sayede hakediş maaşa doğru yansır.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="closeModals()" class="flex-1 rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">İptal</button>
                    <button type="submit" class="flex-[2] rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-500/30 transition hover:scale-[1.02]" :class="{'opacity-50 pointer-events-none': isSaving}">
                        <span x-text="isSaving ? 'Kaydediliyor...' : 'Kaydet'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif


{{-- ===== YAZDIRMAYA ÖZEL TABLO (Ekranda gizli, sadece PDF/Print'te görünür) ===== --}}
@if($selectedCustomer)
<div id="print-only-table">
    <h2><b>{{ $selectedCustomer->company_name }}</b> — {{ $monthOptions[$selectedMonth] }} {{ $selectedYear }} Puantaj Raporu</h2>
    <p class="print-subtitle">Oluşturulma: {{ now()->format('d.m.Y H:i') }} • Güzergah Sayısı: {{ $serviceRoutes->count() }}</p>

    <table>
        <thead>
            <tr>
                <th class="route-col">GÜZERGAH</th>
                @foreach($monthDays as $day)
                    <th class="day-col">{{ $day['date']->format('d') }}<br><span style="font-size:5pt;font-weight:normal;">{{ mb_substr($day['day_name'],0,3) }}</span></th>
                @endforeach
                <th class="day-col">TOPLAM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($serviceRoutes as $route)
                @php $routeSum = 0; @endphp
                <tr x-show="!hiddenRoutes.includes('{{ $route->id }}')">
                    <td class="route-col">
                        <b>{{ $route->route_name }}</b><br>
                        <span style="font-size:5.5pt;color:#666;">
                            @if($route->service_type !== 'evening')
                            S: {{ $route->morningVehicle->plate ?? '-' }} 
                            @endif
                            @if($route->service_type === 'both' || $route->service_type === 'shift') / @endif
                            @if($route->service_type !== 'morning')
                            A: {{ $route->eveningVehicle->plate ?? '-' }}
                            @endif
                        </span>
                    </td>
                    @foreach($monthDays as $day)
                        @php
                            $cell = $matrix[$day['date_key']][$route->id] ?? null;
                            $price = $cell['value'] ?? '';
                            if ($price !== '' && $price !== null) $routeSum += (float)$price;
                            $cellClass = '';
                            if ($day['is_holiday']) $cellClass = 'holiday-cell';
                            elseif ($day['is_weekend']) $cellClass = 'weekend-cell';
                        @endphp
                        <td class="price-cell {{ $cellClass }}">{{ $price !== '' && $price !== null ? number_format((float)$price, 0, ',', '.') : '' }}</td>
                    @endforeach
                    <td class="price-cell" style="background:#f0f9ff !important;-webkit-print-color-adjust:exact;print-color-adjust:exact;"><b>{{ number_format($routeSum, 0, ',', '.') }}</b></td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td class="route-col" style="text-align:right;"><b>ARA TOPLAM</b></td>
                <td colspan="{{ count($monthDays) + 1 }}" style="text-align:left;padding-left:8px;">₺{{ number_format($summary['subtotal'], 2, ',', '.') }}</td>
            </tr>
            <tr class="summary-row">
                <td class="route-col" style="text-align:right;"><b>KDV (%{{ $summary['vat_rate'] }})</b></td>
                <td colspan="{{ count($monthDays) + 1 }}" style="text-align:left;padding-left:8px;">₺{{ number_format($summary['vat_amount'], 2, ',', '.') }}</td>
            </tr>
            @if($summary['withholding_amount'] > 0)
            <tr class="summary-row">
                <td class="route-col" style="text-align:right;"><b>TEVKİFAT (%{{ $summary['withholding_rate'] }})</b></td>
                <td colspan="{{ count($monthDays) + 1 }}" style="text-align:left;padding-left:8px;">₺{{ number_format($summary['withholding_amount'], 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="summary-row">
                <td class="route-col" style="text-align:right;"><b>NET TOPLAM</b></td>
                <td colspan="{{ count($monthDays) + 1 }}" style="text-align:left;padding-left:8px;font-size:11pt;"><b>₺{{ number_format($summary['net_total'], 2, ',', '.') }}</b></td>
            </tr>
        </tbody>
    </table>
</div>
@endif
</div>
@endif

<script>
    function puantajMatrix() {
        return {
            openHizliMod: false,
            isConfirmModalOpen: false,
            isVehicleModalOpen: false,
            showDriverSelect: false,
            isSaving: false,
            hiddenRoutes: [],
            totalRoutes: {{ $serviceRoutes->count() ?? 0 }},
            activeCell: {},
            enteredPrice: '',
            formData: {
                route_id: null,
                date: null,
                morning_id: null,
                evening_id: null,
                driver_id: null,
                morning_driver_id: null,
                evening_driver_id: null,
                status: 'Yapıldı'
            },
            
            startPriceEntry(cellData, priceValue) {
                // Fiyat boş veya 0 ise direkt silme işlemini yap (modal açma)
                if (priceValue === '' || priceValue === null || priceValue === '0' || parseFloat(priceValue) === 0) {
                    this.activeCell = cellData;
                    this.enteredPrice = ''; // Backend'e boş gönderince silecek
                    this.formData = {
                        route_id: cellData.route_id,
                        date: cellData.date,
                        morning_id: '',
                        evening_id: '',
                        driver_id: '',
                        morning_driver_id: '',
                        evening_driver_id: '',
                        status: 'İptal'
                    };
                    this.executeSave();
                    return;
                }
                
                this.activeCell = cellData;
                this.enteredPrice = priceValue;
                this.formData = {
                    route_id: cellData.route_id,
                    date: cellData.date,
                    morning_id: cellData.morning_id || cellData.default_morning_id,
                    evening_id: cellData.evening_id || cellData.default_evening_id,
                    driver_id: cellData.driver_id || '',
                    morning_driver_id: cellData.morning_driver_id || '',
                    evening_driver_id: cellData.evening_driver_id || '',
                    status: cellData.status || 'Yapıldı'
                };
                this.showDriverSelect = !!cellData.driver_id || !!cellData.morning_driver_id || !!cellData.evening_driver_id;
                
                if (this.openHizliMod) {
                    this.saveQuick();
                } else {
                    // Show confirm modal
                    this.isConfirmModalOpen = true;
                    this.isVehicleModalOpen = false;
                }
            },
            
            openVehicleSelectModal() {
                this.isConfirmModalOpen = false;
                this.isVehicleModalOpen = true;
            },
            
            closeModals() {
                this.isConfirmModalOpen = false;
                this.isVehicleModalOpen = false;
            },
            
            saveQuick() {
                // Keep default IDs
                this.formData.morning_id = this.activeCell.default_morning_id;
                this.formData.evening_id = this.activeCell.default_evening_id;
                this.formData.driver_id = ''; // reset legacy
                this.formData.morning_driver_id = ''; // reset driver
                this.formData.evening_driver_id = ''; // reset driver
                this.executeSave();
            },
            
            saveWithVehicles() {
                this.executeSave();
            },
            
            async executeSave() {
                this.isSaving = true;
                try {
                    const response = await fetch('{{ route('trips.upsert-cell') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            service_route_id: this.formData.route_id,
                            trip_date: this.formData.date,
                            trip_price: this.enteredPrice,
                            morning_vehicle_id: this.formData.morning_id,
                            evening_vehicle_id: this.formData.evening_id,
                            driver_id: this.formData.driver_id,
                            morning_driver_id: this.formData.morning_driver_id,
                            evening_driver_id: this.formData.evening_driver_id,
                            trip_status: this.formData.status
                        })
                    });
                    
                    const data = await response.json();
                    if (data.success || data.deleted) {
                        this.closeModals();
                        // Kaydetmeden önce scroll pozisyonunu sakla
                        let container = document.querySelector('.matrix-container');
                        if (container) {
                            sessionStorage.setItem('puantajScrollLeft', container.scrollLeft);
                            sessionStorage.setItem('puantajScrollTop', window.scrollY || document.documentElement.scrollTop);
                        }
                        window.location.reload();
                    } else {
                        alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu'));
                    }
                } catch (error) {
                    alert('Sunucu ile bağlantı kurulamadı.');
                } finally {
                    this.isSaving = false;
                }
            }
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        let container = document.querySelector('.matrix-container');

        // Kayıt sonrası sayfa yenileniyorsa önceki scroll pozisyonunu geri yükle
        let savedScrollLeft = sessionStorage.getItem('puantajScrollLeft');
        let savedScrollTop = sessionStorage.getItem('puantajScrollTop');

        if (savedScrollLeft !== null && container) {
            // Kaydedilen pozisyonu geri yükle, otomatik kaydırma yapma
            container.scrollLeft = parseInt(savedScrollLeft, 10);
            sessionStorage.removeItem('puantajScrollLeft');

            if (savedScrollTop !== null) {
                window.scrollTo(0, parseInt(savedScrollTop, 10));
                sessionStorage.removeItem('puantajScrollTop');
            }
        } else if (container) {
            // İlk giriş: en son işlenen güne otomatik kaydır
            let lastRecordDay = 0;
            document.querySelectorAll('td[data-has-record="true"]').forEach(td => {
                let day = parseInt(td.getAttribute('data-date'));
                if (day > lastRecordDay) lastRecordDay = day;
            });
            
            let targetDay = lastRecordDay + 1;
            if (targetDay > 31) targetDay = 31;
            
            let targetDayStr = targetDay.toString().padStart(2, '0');
            let targetHeader = document.getElementById('day-header-' + targetDayStr);
            
            if (targetHeader) {
                // Sticky column is 250px, we scroll so the target is visible next to it
                container.scrollLeft = targetHeader.offsetLeft - 300;
            }
        }
    });

    function printPuantaj() {
        var printContent = document.getElementById('print-only-table');
        if (!printContent) { alert('Yazdırılacak veri bulunamadı.'); return; }
        
        var printWindow = window.open('', '_blank', 'width=1200,height=800');
        printWindow.document.write('<html><head><title>Puantaj Raporu</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@page { size: A4 landscape; margin: 4mm; }');
        printWindow.document.write('body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 6px; background: white; }');
        printWindow.document.write('h2 { font-size: 13pt; margin: 0 0 2px 0; color: #1e293b; }');
        printWindow.document.write('.print-subtitle { font-size: 8pt; color: #666; margin: 0 0 6px 0; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; table-layout: fixed; }');
        printWindow.document.write('th, td { border: 1px solid #555; padding: 2px 1px; text-align: center; font-size: 6.5pt; overflow: hidden; }');
        printWindow.document.write('th { background: #1e293b; color: white; font-weight: bold; }');
        printWindow.document.write('.route-col { width: 10%; text-align: left; padding-left: 3px; font-size: 6pt; word-wrap: break-word; overflow-wrap: break-word; }');
        printWindow.document.write('.day-col { font-weight: bold; }');
        printWindow.document.write('.price-cell { font-size: 7pt; font-weight: bold; }');
        printWindow.document.write('.weekend-cell { background: #fff1f2; }');
        printWindow.document.write('.holiday-cell { background: #fae8ff; }');
        printWindow.document.write('.summary-row td { font-weight: bold; font-size: 8pt; border-top: 2px solid #000; }');
        printWindow.document.write('</style></head><body>');
        printWindow.document.write(printContent.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
        };
    }
</script>
@endsection
