@extends('layouts.app')

@section('title', 'Yakıt Kaydı Düzenle')
@section('subtitle', 'Mevcut yakıt kaydını güncelle')

@section('content')
@php
    $fuelTypeOptions = ['Dizel', 'Benzin', 'LPG', 'Eurodiesel', 'AdBlue'];
    $stations = collect($stations ?? []);
@endphp

<div class="space-y-6">
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
        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-amber-50/40 px-6 py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-[26px] font-bold tracking-tight text-slate-900">Yakıt Kaydı Düzenle</h1>
                    <p class="mt-1 text-sm text-slate-500">Seçili yakıt kaydının araç, km, litre ve maliyet bilgilerini güncelle</p>
                </div>

                <a href="{{ route('fuels.index') }}"
                   class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    Listeye Dön
                </a>
            </div>
        </div>

        <form action="{{ route('fuels.update', $fuel) }}" method="POST" class="p-6 space-y-6" id="fuelEditForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Araç</label>
                    <select name="vehicle_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                        <option value="">Araç seçiniz</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $fuel->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->plate }} - {{ $vehicle->brand }} {{ $vehicle->model }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Tarih</label>
                    <input type="date"
                           name="date"
                           value="{{ old('date', \Carbon\Carbon::parse($fuel->date)->format('Y-m-d')) }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Yakıt KM</label>
                    <input type="number"
                           name="km"
                           value="{{ old('km', $fuel->km) }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Litre</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           name="liters"
                           id="liters"
                           value="{{ old('liters', $fuel->liters) }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Birim Fiyat</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           name="price_per_liter"
                           id="price_per_liter"
                           value="{{ old('price_per_liter', $fuel->price_per_liter) }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Pompa Tutarı (KDV Dahil)</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           name="total_cost"
                           id="total_cost"
                           value="{{ old('total_cost', $fuel->total_cost) }}"
                           readonly
                           class="w-full rounded-2xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 font-semibold text-emerald-700 shadow-sm outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Yakıt Türü</label>
                    <select name="fuel_type" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                        @foreach($fuelTypeOptions as $fuelType)
                            <option value="{{ $fuelType }}" {{ old('fuel_type', $fuel->fuel_type ?? 'Dizel') === $fuelType ? 'selected' : '' }}>
                                {{ $fuelType }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Yakıt İstasyonu</label>
                    <select name="fuel_station_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                        <option value="">İstasyon seçiniz</option>
                        @foreach($stations as $station)
                            <option value="{{ $station->id }}" {{ old('fuel_station_id', $fuel->fuel_station_id ?? null) == $station->id ? 'selected' : '' }}>
                                {{ $station->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">İstasyon Adı (manuel)</label>
                    <input type="text"
                           name="station_name"
                           value="{{ old('station_name', $fuel->station_name ?? '') }}"
                           class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Not</label>
                <textarea name="notes"
                          rows="4"
                          class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100">{{ old('notes', $fuel->notes) }}</textarea>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('fuels.index') }}"
                   class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Vazgeç
                </a>

                <button type="submit"
                        class="rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-amber-200/60 transition hover:scale-[1.01]">
                    Kaydı Güncelle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const litersInput = document.getElementById('liters');
        const priceInput = document.getElementById('price_per_liter');
        const totalInput = document.getElementById('total_cost');

        function calculateTotal() {
            const liters = parseFloat(litersInput.value || 0);
            const price = parseFloat(priceInput.value || 0);
            const total = liters * price;
            totalInput.value = total > 0 ? total.toFixed(2) : '';
        }

        litersInput.addEventListener('input', calculateTotal);
        priceInput.addEventListener('input', calculateTotal);
        calculateTotal();
    });
</script>
@endsection
