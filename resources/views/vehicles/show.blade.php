@extends('layouts.app')

@section('title', 'Araç Detayı')
@section('subtitle', 'Araç yönetim merkezi')

@section('content')

@php
    use Illuminate\Support\Collection;

    $activeTab = request('tab', 'general');

    $displayColor = $vehicle->color === 'Diğer'
        ? ($vehicle->other_color ?: 'Diğer')
        : ($vehicle->color ?: '-');

    $profitColorClass = $profit >= 0
        ? 'from-emerald-500 to-teal-500'
        : 'from-rose-500 to-pink-500';

    $profitTextClass = $profit >= 0
        ? 'text-emerald-600'
        : 'text-rose-600';

    $activeVehicleDocuments = collect($activeVehicleDocuments ?? []);
    $archivedVehicleDocuments = collect($archivedVehicleDocuments ?? []);
    $recentFuels = collect($recentFuels ?? []);
    $assignedDrivers = collect($assignedDrivers ?? []);
    $recentTrips = collect($recentTrips ?? []);
    $vehicleMaintenances = collect($vehicleMaintenances ?? []);
    $vehiclePenalties = collect($vehiclePenalties ?? []);
    $vehicleImages = collect($vehicleImages ?? []);

    $featuredImage = $vehicleImages->firstWhere('is_featured', true) ?? $vehicleImages->first();

    $driverFullName = null;
    if (!empty($primaryDriver)) {
        $driverFullName = data_get($primaryDriver, 'full_name')
            ?? data_get($primaryDriver, 'name')
            ?? trim((data_get($primaryDriver, 'first_name', '') . ' ' . data_get($primaryDriver, 'last_name', '')));
        $driverFullName = trim($driverFullName) !== '' ? trim($driverFullName) : null;
    }

    $driverPhone = !empty($primaryDriver)
        ? (
            data_get($primaryDriver, 'phone')
            ?? data_get($primaryDriver, 'phone_number')
            ?? data_get($primaryDriver, 'mobile_phone')
            ?? data_get($primaryDriver, 'gsm')
        )
        : null;

    $formattedKm = number_format((float) ($currentKm ?? 0), 0, ',', '.');

    $dateCard = function ($date) {
        if (!$date) {
            return ['text' => '-', 'status' => 'Tanımsız', 'class' => 'bg-slate-100 text-slate-600'];
        }

        $days = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($date)->startOfDay(), false);

        if ($days < 0) {
            return [
                'text' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                'status' => 'Süresi Geçmiş',
                'class' => 'bg-rose-100 text-rose-700',
            ];
        }

        if ($days <= 30) {
            return [
                'text' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
                'status' => $days . ' gün kaldı',
                'class' => 'bg-amber-100 text-amber-700',
            ];
        }

        return [
            'text' => \Carbon\Carbon::parse($date)->format('d.m.Y'),
            'status' => $days . ' gün kaldı',
            'class' => 'bg-emerald-100 text-emerald-700',
        ];
    };

    // Kritik Takvim: TÜM belgelerden (aktif + arşiv) en güncel olanı her tür için oku
    $allDocs = collect($activeVehicleDocuments ?? [])->merge(collect($archivedVehicleDocuments ?? []));
    $latestDocByType = $allDocs->groupBy('document_type')->map(function ($group) {
        return $group->sortByDesc(function ($d) { return $d->end_date ?? $d->created_at; })->first();
    });

    $inspectionDoc = $latestDocByType->get('Muayene');
    $exhaustDoc    = $latestDocByType->get('Egzoz');
    $insuranceDoc  = $latestDocByType->get('Sigorta');
    $immDoc        = $latestDocByType->get('İMM Poliçesi') ?? $latestDocByType->get('İMM POLİÇESİ');

    // Önce documents'tan oku, yoksa vehicle model alanlarına fallback yap
    $inspectionInfo = $dateCard($inspectionDoc?->end_date ?? $vehicle->inspection_date ?? null);
    $exhaustInfo    = $dateCard($exhaustDoc?->end_date ?? $vehicle->exhaust_date ?? null);
    $insuranceInfo  = $dateCard($insuranceDoc?->end_date ?? $vehicle->insurance_end_date ?? null);
    $kaskoInfo      = $dateCard($vehicle->kaskoExpiryDate());
    $immInfo        = $dateCard($immDoc?->end_date ?? null);

    $tabClass = function ($key) use ($activeTab) {
        return $activeTab === $key
            ? 'inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg'
            : 'inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition';
    };

    $remainingBadge = function ($endDate) {
        if (!$endDate) {
            return ['text' => '-', 'class' => 'bg-slate-100 text-slate-600'];
        }

        $days = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($endDate)->startOfDay(), false);

        if ($days < 0) {
            return ['text' => abs($days) . ' gün geçti', 'class' => 'bg-rose-100 text-rose-700'];
        }

        if ($days <= 30) {
            return ['text' => $days . ' gün kaldı', 'class' => 'bg-amber-100 text-amber-700'];
        }

        return ['text' => $days . ' gün kaldı', 'class' => 'bg-emerald-100 text-emerald-700'];
    };
@endphp

<div class="space-y-10 pb-24">
    @if(session('success'))
        <div class="rounded-[24px] border border-emerald-200 bg-emerald-50/80 backdrop-blur-sm px-6 py-4 text-sm font-bold text-emerald-800 shadow-xl shadow-emerald-500/10 flex items-center gap-3">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white text-[10px]">✓</span>
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-[24px] border border-rose-200 bg-rose-50/80 backdrop-blur-sm px-6 py-4 text-sm font-bold text-rose-800 shadow-xl shadow-rose-500/10">
            <div class="mb-3 flex items-center gap-2">
                <span class="text-xl">⚠️</span>
                <span>Lütfen aşağıdaki hataları düzeltin:</span>
            </div>
            <ul class="list-disc space-y-1 pl-10 opacity-80">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Colorful Header Section --}}
    <div class="relative">
        @include('vehicles.partials.header')
    </div>

    {{-- Vibrant Tabs Section --}}
    <div class="relative">
        @include('vehicles.partials.tabs')
    </div>

    {{-- AI Assistant Partial --}}
    @include('vehicles.partials.ai_assistant')
</div>

@include('vehicles.partials.scripts')

@endsection
