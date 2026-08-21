@extends('layouts.app')

@section('title', 'Araç Düzenle')
@section('subtitle', 'Araç bilgilerini güncelleyin')

@section('content')

<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Araç Düzenle</h2>
            <p class="text-sm text-slate-500 mt-1">
                {{ $vehicle->plate }} plakalı aracı güncelliyorsunuz.
            </p>
        </div>

        <a href="{{ route('vehicles.index') }}"
           class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
            ← Araç Listesine Dön
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <div class="font-semibold mb-2">Lütfen aşağıdaki hataları düzeltin:</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('vehicles.update', $vehicle) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-[30px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                <h3 class="text-lg font-bold text-slate-800">Genel Araç Bilgileri</h3>
                <p class="text-sm text-slate-500 mt-1">Temel kimlik, kullanım ve sınıflandırma alanları</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Plaka</label>
                    <input type="text" name="plate" value="{{ old('plate', $vehicle->plate) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                    @error('plate')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Marka</label>
                    <input type="text" name="brand" value="{{ old('brand', $vehicle->brand) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Model</label>
                    <input type="text" name="model" value="{{ old('model', $vehicle->model) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Araç Tipi</label>
                    <select name="vehicle_type"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                        <option value="">Seçiniz</option>
                        <option value="Minibüs" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Minibüs' ? 'selected' : '' }}>Minibüs</option>
                        <option value="Midibüs" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Midibüs' ? 'selected' : '' }}>Midibüs</option>
                        <option value="Otobüs" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Otobüs' ? 'selected' : '' }}>Otobüs</option>
                        <option value="Binek Araç" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Binek Araç' ? 'selected' : '' }}>Binek Araç</option>
                        <option value="Panelvan" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Panelvan' ? 'selected' : '' }}>Panelvan</option>
                        <option value="Kamyonet" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Kamyonet' ? 'selected' : '' }}>Kamyonet</option>
                        <option value="Diğer" {{ old('vehicle_type', $vehicle->vehicle_type) == 'Diğer' ? 'selected' : '' }}>Diğer</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Araç Paketi</label>
                    <select name="vehicle_package"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                        <option value="">Seçiniz</option>
                        <option value="Hususi" {{ old('vehicle_package', $vehicle->vehicle_package) == 'Hususi' ? 'selected' : '' }}>Hususi</option>
                        <option value="Yolcu Nakli" {{ old('vehicle_package', $vehicle->vehicle_package) == 'Yolcu Nakli' ? 'selected' : '' }}>Yolcu Nakli</option>
                        <option value="Okul Taşıtı" {{ old('vehicle_package', $vehicle->vehicle_package) == 'Okul Taşıtı' ? 'selected' : '' }}>Okul Taşıtı</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Model Yılı</label>
                    <input type="number" name="model_year" value="{{ old('model_year', $vehicle->model_year) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Tescil Tarihi</label>
                    <input type="date" name="registration_date" value="{{ old('registration_date', optional($vehicle->registration_date)->format('Y-m-d')) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Koltuk Sayısı</label>
                    <input type="number" name="seat_count" value="{{ old('seat_count', $vehicle->seat_count) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Vites Türü</label>
                    <select name="gear_type"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                        <option value="">Seçiniz</option>
                        <option value="Manuel" {{ old('gear_type', $vehicle->gear_type) == 'Manuel' ? 'selected' : '' }}>Manuel</option>
                        <option value="Otomatik" {{ old('gear_type', $vehicle->gear_type) == 'Otomatik' ? 'selected' : '' }}>Otomatik</option>
                        <option value="Yarı Otomatik" {{ old('gear_type', $vehicle->gear_type) == 'Yarı Otomatik' ? 'selected' : '' }}>Yarı Otomatik</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Yakıt Tipi</label>
                    <select name="fuel_type"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                        <option value="">Seçiniz</option>
                        <option value="Dizel" {{ old('fuel_type', $vehicle->fuel_type) == 'Dizel' ? 'selected' : '' }}>Dizel</option>
                        <option value="Benzin" {{ old('fuel_type', $vehicle->fuel_type) == 'Benzin' ? 'selected' : '' }}>Benzin</option>
                        <option value="LPG" {{ old('fuel_type', $vehicle->fuel_type) == 'LPG' ? 'selected' : '' }}>LPG</option>
                        <option value="Benzin + LPG" {{ old('fuel_type', $vehicle->fuel_type) == 'Benzin + LPG' ? 'selected' : '' }}>Benzin + LPG</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Renk</label>
                    <select name="color" id="color"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                        <option value="">Seçiniz</option>
                        <option value="Beyaz" {{ old('color', $vehicle->color) == 'Beyaz' ? 'selected' : '' }}>Beyaz</option>
                        <option value="Siyah" {{ old('color', $vehicle->color) == 'Siyah' ? 'selected' : '' }}>Siyah</option>
                        <option value="Diğer" {{ old('color', $vehicle->color) == 'Diğer' ? 'selected' : '' }}>Diğer</option>
                    </select>
                </div>

                <div id="other-color-wrapper" class="{{ old('color', $vehicle->color) == 'Diğer' ? '' : 'hidden' }}">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Diğer Renk Adı</label>
                    <input type="text" name="other_color" value="{{ old('other_color', $vehicle->other_color) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 w-full cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $vehicle->is_active) ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">Araç Aktif</div>
                            <div class="text-xs text-slate-500">Sistemde aktif olarak kullanılsın</div>
                        </div>
                    </label>
                </div>

            </div>
        </div>

        <div class="rounded-[30px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                <h3 class="text-lg font-bold text-slate-800">Ruhsat ve Sahip Bilgileri</h3>
                <p class="text-sm text-slate-500 mt-1">Randevu ve ruhsat işlemlerinde gerekli alanlar</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Ruhsat Belge Seri No</label>
                    <input type="text" name="license_serial_no" value="{{ old('license_serial_no', $vehicle->license_serial_no) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Ruhsat Sahibi</label>
                    <input type="text" name="license_owner" value="{{ old('license_owner', $vehicle->license_owner) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Ruhsat Sahibi Vergi / T.C. No</label>
                    <input type="text" name="owner_tax_or_tc_no" value="{{ old('owner_tax_or_tc_no', $vehicle->owner_tax_or_tc_no) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>
            </div>
        </div>

        <div class="rounded-[30px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                <h3 class="text-lg font-bold text-slate-800">Teknik Bilgiler</h3>
                <p class="text-sm text-slate-500 mt-1">Araç kimlik ve teknik numaralar</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Motor No</label>
                    <input type="text" name="engine_no" value="{{ old('engine_no', $vehicle->engine_no) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Şasi No</label>
                    <input type="text" name="chassis_no" value="{{ old('chassis_no', $vehicle->chassis_no) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>
            </div>
        </div>

        <div class="rounded-[30px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                <h3 class="text-lg font-bold text-slate-800">Belge ve Tarih Bilgileri</h3>
                <p class="text-sm text-slate-500 mt-1">Takip edilecek resmi tarih alanları</p>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Muayene Tarihi</label>
                    <input type="date" name="inspection_date" value="{{ old('inspection_date', optional($vehicle->inspection_date)->format('Y-m-d')) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Egzoz Tarihi</label>
                    <input type="date" name="exhaust_date" value="{{ old('exhaust_date', optional($vehicle->exhaust_date)->format('Y-m-d')) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Sigorta Bitiş Tarihi</label>
                    <input type="date" name="insurance_end_date" value="{{ old('insurance_end_date', optional($vehicle->insurance_end_date)->format('Y-m-d')) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Kasko Bitiş Tarihi</label>
                    <input type="date" name="kasko_end_date" value="{{ old('kasko_end_date', optional($vehicle->kasko_end_date)->format('Y-m-d')) }}"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">
                </div>
            </div>
        </div>

        <div class="rounded-[30px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
                <h3 class="text-lg font-bold text-slate-800">Notlar</h3>
                <p class="text-sm text-slate-500 mt-1">Araçla ilgili ek açıklamalar</p>
            </div>

            <div class="p-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Açıklama / Not</label>
                <textarea name="notes" rows="5"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100 outline-none">{{ old('notes', $vehicle->notes) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('vehicles.index') }}"
               class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                Vazgeç
            </a>

            <button type="submit"
                class="inline-flex items-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg hover:scale-[1.02] transition">
                Güncelle
            </button>
        </div>
    </form>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const colorSelect = document.getElementById('color');
        const otherColorWrapper = document.getElementById('other-color-wrapper');

        function toggleOtherColor() {
            if (!colorSelect || !otherColorWrapper) return;

            if (colorSelect.value === 'Diğer') {
                otherColorWrapper.classList.remove('hidden');
            } else {
                otherColorWrapper.classList.add('hidden');
            }
        }

        if (colorSelect) {
            colorSelect.addEventListener('change', toggleOtherColor);
            toggleOtherColor();
        }
    });
</script>

@endsection
