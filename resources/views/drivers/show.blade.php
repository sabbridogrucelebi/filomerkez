@extends('layouts.app')

@section('title', 'Personel Detayı')
@section('subtitle', 'Personel bilgileri, belgeler, maaşlar ve resimler')

@section('content')
    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    @php
        $documentBadge = match($documentStatus) {
            'expired' => ['label' => 'Süresi Geçmiş', 'class' => 'bg-rose-100 text-rose-700'],
            'expiring' => ['label' => 'Yakında Bitecek', 'class' => 'bg-amber-100 text-amber-700'],
            default => ['label' => 'Uygun', 'class' => 'bg-emerald-100 text-emerald-700'],
        };

        $phoneForLink = $driver->phone ? preg_replace('/[^0-9]/', '', $driver->phone) : null;
        if ($phoneForLink && str_starts_with($phoneForLink, '0')) {
            $phoneForLink = '90' . substr($phoneForLink, 1);
        }

        $tabClass = function ($tab) use ($activeTab) {
            $active = $activeTab === $tab;

            return $active
                ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-200/70'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50';
        };

        $remainingBadgeClass = function ($document) {
            return match($document->alert_status) {
                'expired' => 'bg-rose-100 text-rose-700',
                'expiring' => 'bg-amber-100 text-amber-700',
                'active_soon' => 'bg-orange-100 text-orange-700',
                'active' => 'bg-emerald-100 text-emerald-700',
                default => 'bg-slate-100 text-slate-600',
            };
        };
    @endphp

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-[24px] bg-emerald-100 p-4 text-sm font-bold text-emerald-700 no-print">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-[24px] bg-rose-100 p-4 text-sm font-bold text-rose-700 no-print">
                {{ session('error') }}
            </div>
        @endif
        <div class="grid gap-4 xl:grid-cols-1">
            <div class="xl:col-span-1 space-y-5">
                <div class="rounded-[30px] border border-slate-200/60 bg-white/90 shadow-xl overflow-hidden">
                    <div class="flex flex-col gap-5 border-b border-slate-100 px-6 py-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-[22px] bg-gradient-to-br from-blue-500 to-indigo-600 text-3xl text-white shadow-lg">
                                🧑‍✈️
                            </div>

                            <div>
                                <div class="text-[2rem] font-extrabold tracking-tight text-slate-900">
                                    {{ $driver->full_name }}
                                </div>
                                <div class="mt-1 text-sm font-medium text-slate-500">
                                    {{ $driver->vehicle?->plate ?? 'Araç atanmamış' }}
                                    @if($driver->vehicle)
                                        • {{ trim(($driver->vehicle->brand ?? '') . ' ' . ($driver->vehicle->model ?? '')) }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center rounded-full {{ $driver->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }} px-4 py-2 text-sm font-semibold">
                                {{ $driver->is_active ? 'Aktif Personel' : 'Pasif Personel' }}
                            </span>


                            <a href="{{ route('drivers.edit', $driver) }}"
                               class="rounded-2xl bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100">
                                Düzenle
                            </a>

                            <button type="button" onclick="openLeaveModal()"
                                    class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                🛑 İşten Ayrıldı
                            </button>

                            <button type="button" onclick="openChangeVehicleModal()"
                                    class="rounded-2xl bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                                🚐 Araç Değiştir
                            </button>

                            <a href="{{ route('drivers.index') }}"
                               class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Listeye Dön
                            </a>
                        </div>
                    </div>

                    <div class="grid gap-4 px-6 py-5 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-[24px] bg-gradient-to-r from-blue-500 to-sky-500 p-5 text-white">
                            <div class="text-sm text-white/80">Ana Maaş</div>
                            <div class="mt-2 text-2xl font-extrabold">
                                {{ $driver->base_salary ? number_format($driver->base_salary, 2, ',', '.') . ' ₺' : '-' }}
                            </div>
                            <div class="mt-1 text-xs text-white/75">Tanımlı sabit maaş</div>
                        </div>

                        <div class="rounded-[24px] bg-gradient-to-r from-orange-500 to-amber-500 p-5 text-white">
                            <div class="text-sm text-white/80">Belge Durumu</div>
                            <div class="mt-2 text-2xl font-extrabold">{{ $documentBadge['label'] }}</div>
                            <div class="mt-1 text-xs text-white/75">Belge ve döküman takibi</div>
                        </div>

                        <div class="rounded-[24px] bg-gradient-to-r from-violet-500 to-fuchsia-500 p-5 text-white">
                            <div class="text-sm text-white/80">Toplam Bordro</div>
                            <div class="mt-2 text-2xl font-extrabold">{{ $totalPayrollCount }}</div>
                            <div class="mt-1 text-xs text-white/75">Kayıtlı maaş dönemi</div>
                        </div>

                        <div class="rounded-[24px] bg-gradient-to-r from-rose-500 to-pink-500 p-5 text-white">
                            <div class="text-sm text-white/80">Toplam Evrak</div>
                            <div class="mt-2 text-2xl font-extrabold">{{ $documentCount }}</div>
                            <div class="mt-1 text-xs text-white/75">Belge ve resim toplamı</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[30px] border border-slate-200/60 bg-white/90 shadow-xl overflow-hidden">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-xl text-white shadow-lg">
                                📁
                            </div>
                            <div>
                                <h3 class="text-[1.85rem] font-extrabold tracking-tight text-slate-900">Personel Yönetim Sekmeleri</h3>
                                <p class="mt-1 text-sm font-medium text-slate-500">Personel bilgilerini modüler ve profesyonel şekilde yönetin</p>
                            </div>
                        </div>

                        <div class="inline-flex items-center rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200">
                            ● Aktif personel yönetim merkezi
                        </div>
                    </div>

                    <div class="space-y-5 px-6 py-5">
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('drivers.show', ['driver' => $driver->id, 'tab' => 'general']) }}"
                               class="rounded-2xl px-5 py-3 text-sm font-semibold transition {{ $tabClass('general') }}">
                                📋 Genel Bilgiler
                            </a>

                            <a href="{{ route('drivers.show', ['driver' => $driver->id, 'tab' => 'documents']) }}"
                               class="rounded-2xl px-5 py-3 text-sm font-semibold transition {{ $tabClass('documents') }}">
                                📄 Belge ve Dökümanlar
                            </a>

                            <a href="{{ route('drivers.show', ['driver' => $driver->id, 'tab' => 'payrolls']) }}"
                               class="rounded-2xl px-5 py-3 text-sm font-semibold transition {{ $tabClass('payrolls') }}">
                                💵 Maaşlar
                            </a>

                            <a href="{{ route('drivers.show', ['driver' => $driver->id, 'tab' => 'images']) }}"
                               class="rounded-2xl px-5 py-3 text-sm font-semibold transition {{ $tabClass('images') }}">
                                🖼️ Resimler
                            </a>

                            <a href="{{ route('drivers.show', ['driver' => $driver->id, 'tab' => 'leaves']) }}"
                               class="rounded-2xl px-5 py-3 text-sm font-semibold transition {{ $tabClass('leaves') }}">
                                🏖️ İzinler
                            </a>
                        </div>

                        @if($activeTab === 'general')
                            <div class="grid gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5">
                                        <h4 class="text-xl font-bold text-slate-900">Genel Personel Bilgileri</h4>
                                        <p class="mt-1 text-sm text-slate-500">Personelin kimlik, iletişim ve iş bilgileri</p>
                                    </div>

                                    <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Ad Soyad</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->full_name }}</div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">TC Kimlik No</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->tc_no ?: '-' }}</div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Telefon</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->phone ?: '-' }}</div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">E-posta</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->email ?: '-' }}</div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Doğum Tarihi</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">
                                                {{ optional($driver->birth_date)->format('d.m.Y') ?: '-' }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ !is_null($driverAge) ? $driverAge . ' yaş' : '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">İşe Giriş Tarihi</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">
                                                {{ optional($driver->start_date)->format('d.m.Y') ?: '-' }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ !is_null($serviceYears) ? $serviceYears . ' yıl hizmet' : '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Bağlı Araç</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->vehicle?->plate ?? '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                @if($driver->vehicle)
                                                    {{ trim(($driver->vehicle->brand ?? '') . ' ' . ($driver->vehicle->model ?? '')) }}
                                                @else
                                                    Araç atanmamış
                                                @endif
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Ehliyet Sınıfı</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">{{ $driver->license_class ?: '-' }}</div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Ana Maaş</div>
                                            <div class="mt-2 text-base font-bold text-slate-900">
                                                {{ $driver->base_salary ? number_format($driver->base_salary, 2, ',', '.') . ' ₺' : '-' }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Durum</div>
                                            <div class="mt-2">
                                                <span class="inline-flex rounded-full {{ $driver->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }} px-3 py-1 text-xs font-bold">
                                                    {{ $driver->is_active ? 'Aktif' : 'Pasif' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-slate-100 p-6">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="rounded-2xl bg-slate-50 p-4">
                                                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Adres</div>
                                                <div class="mt-2 text-sm text-slate-700">{{ $driver->address ?: '-' }}</div>
                                            </div>

                                            <div class="rounded-2xl bg-slate-50 p-4">
                                                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Notlar</div>
                                                <div class="mt-2 text-sm text-slate-700">{{ $driver->notes ?: '-' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(isset($vehicleHistory) && $vehicleHistory->count() > 0)
                                    <div class="border-t border-slate-100 p-6">
                                        <div class="mb-4">
                                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Araç Kullanım Geçmişi</h4>
                                            <p class="mt-1 text-xs text-slate-500">Puantajlara göre personelin kullandığı araçlar ve tarih aralıkları</p>
                                        </div>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            @foreach($vehicleHistory as $history)
                                                <div class="group flex items-center gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4 hover:bg-white hover:shadow-md hover:border-indigo-100 transition-all duration-300">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 font-black text-lg group-hover:scale-110 transition-transform">
                                                        🚐
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="font-black text-slate-800 text-sm">
                                                            @if($history['vehicle'])
                                                                <a href="{{ route('vehicles.show', $history['vehicle']->id) }}" class="hover:text-indigo-600 transition-colors">
                                                                    {{ $history['vehicle']->plate }}
                                                                </a>
                                                            @else
                                                                Bilinmeyen Araç
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 text-xs font-semibold text-slate-500">
                                                            {{ \Carbon\Carbon::parse($history['start'])->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($history['end'])->format('d.m.Y') }}
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="inline-flex flex-col items-end">
                                                            <span class="text-base font-black text-indigo-600">{{ $history['count'] }}</span>
                                                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Sefer</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <div class="xl:col-span-4 space-y-6">
                                    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="border-b border-slate-100 px-6 py-5">
                                            <h4 class="text-xl font-bold text-slate-900">Personel Vitrin Resmi</h4>
                                            <p class="mt-1 text-sm text-slate-500">Seçilen personel resmi burada gösterilir</p>
                                        </div>

                                        <div class="p-6">
                                            @if($featuredImage)
                                                <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50">
                                                    <img src="{{ asset('storage/' . $featuredImage->file_path) }}"
                                                         alt="{{ $featuredImage->document_name }}"
                                                         class="h-[320px] w-full object-cover">
                                                </div>

                                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                    <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Vitrin Resmi</div>
                                                    <div class="mt-2 text-sm font-semibold text-slate-900">{{ $featuredImage->document_name ?: 'Personel Resmi' }}</div>
                                                </div>
                                            @else
                                                <div class="rounded-[24px] border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                                                    <div class="text-4xl">🖼️</div>
                                                    <div class="mt-3 text-base font-semibold text-slate-700">Henüz personel resmi yok</div>
                                                    <div class="mt-1 text-sm text-slate-500">Resimler sekmesinden ilk resmi yükleyebilirsin.</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="border-b border-slate-100 px-6 py-5 bg-amber-50/60">
                                            <h4 class="text-xl font-bold text-slate-900">Belge Durumu Özeti</h4>
                                            <p class="mt-1 text-sm text-slate-500">Takip edilmesi gereken kritik belgeler</p>
                                        </div>

                                        <div class="space-y-3 p-6">
                                            @php
                                                $criticalDocs = $documentDocuments
                                                    ->filter(fn ($doc) => in_array($doc->alert_status, ['expired', 'expiring', 'active_soon'], true))
                                                    ->take(4);
                                            @endphp

                                            @if($criticalDocs->count())
                                                @foreach($criticalDocs as $document)
                                                    <div class="rounded-2xl border p-4 {{ $document->alert_status === 'expired' ? 'bg-rose-50 border-rose-200 text-rose-700' : ($document->alert_status === 'expiring' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-orange-50 border-orange-200 text-orange-700') }}">
                                                        <div class="text-sm font-bold">{{ $document->document_type }}</div>
                                                        <div class="mt-1 text-xs font-medium">
                                                            Bitiş: {{ optional($document->end_date)->format('d.m.Y') ?: '-' }}
                                                        </div>
                                                        <div class="mt-1 text-xs font-semibold">
                                                            {{ $document->alert_text }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">
                                                    Kritik belge uyarısı yok.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($activeTab === 'documents')
                            <div class="grid gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5 flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xl font-bold text-slate-900">Belge ve Dökümanlar</h4>
                                            <p class="mt-1 text-sm text-slate-500">Tüm personel belgeleri tek sekmede yönetilir</p>
                                        </div>

                                        @if($documentDocuments->count())
                                            <a href="{{ route('drivers.documents.zip', $driver) }}"
                                               class="group relative inline-flex items-center gap-3 rounded-2xl bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200/60 transition-all duration-300 hover:scale-[1.04] hover:shadow-xl hover:shadow-indigo-300/50 active:scale-[0.98]"
                                               title="Tüm belgeleri ZIP olarak indir">
                                                {{-- 3D Document Icon --}}
                                                <div class="relative flex items-center justify-center w-9 h-9 transition-transform duration-500 group-hover:rotate-[-8deg] group-hover:scale-110" style="perspective: 200px;">
                                                    <div class="absolute inset-0 rounded-xl bg-white/20 backdrop-blur-sm border border-white/30 shadow-[0_6px_16px_rgba(0,0,0,0.25),inset_0_1px_0_rgba(255,255,255,0.3)] transition-all duration-500 group-hover:shadow-[0_10px_30px_rgba(0,0,0,0.35),inset_0_1px_0_rgba(255,255,255,0.4)]" style="transform: rotateX(8deg) rotateY(-6deg);"></div>
                                                    <svg class="relative w-5 h-5 text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6m0 0l-2.5-2.5M12 17l2.5-2.5"/>
                                                    </svg>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold leading-tight">Toplu İndir</span>
                                                    <span class="text-[10px] font-medium text-white/70 leading-tight">{{ $documentDocuments->count() }} belge · ZIP</span>
                                                </div>
                                                {{-- Shine effect --}}
                                                <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-white/0 via-white/20 to-white/0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                                            </a>
                                        @endif
                                    </div>

                                    @if($documentDocuments->count())
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-6 py-4 text-left font-bold">Belge Türü</th>
                                                        <th class="px-6 py-4 text-left font-bold">Başlangıç</th>
                                                        <th class="px-6 py-4 text-left font-bold">Bitiş</th>
                                                        <th class="px-6 py-4 text-left font-bold">Kalan Süre</th>
                                                        <th class="px-6 py-4 text-left font-bold">Dosya</th>
                                                        <th class="px-6 py-4 text-left font-bold">İşlem</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($documentDocuments as $document)
                                                        <tr class="hover:bg-slate-50/70">
                                                            <td class="px-6 py-5">
                                                                <div class="font-semibold text-slate-900">{{ $document->document_type ?: '-' }}</div>
                                                                <div class="mt-1 text-xs text-slate-500">{{ $document->notes ?: '-' }}</div>
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                {{ optional($document->start_date)->format('d.m.Y') ?: '-' }}
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                {{ optional($document->end_date)->format('d.m.Y') ?: '-' }}
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $remainingBadgeClass($document) }}">
                                                                    {{ $document->alert_text }}
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                @if($document->file_path)
                                                                    <a href="{{ asset('storage/' . $document->file_path) }}"
                                                                       target="_blank"
                                                                       class="text-indigo-600 font-semibold hover:text-indigo-800">
                                                                        Dosyayı Aç
                                                                    </a>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                <form action="{{ route('drivers.documents.destroy', [$driver, $document]) }}" method="POST" onsubmit="return confirm('Bu belgeyi silmek istediğine emin misin?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit"
                                                                            class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                                        Sil
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="p-8 text-center text-sm text-slate-500">Henüz belge kaydı yok.</div>
                                    @endif
                                </div>

                                <div class="xl:col-span-4 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5">
                                        <h4 class="text-xl font-bold text-slate-900">Yeni Belge Yükle</h4>
                                        <p class="mt-1 text-sm text-slate-500">Belge süresi otomatik hesaplanır</p>
                                    </div>

                                    <form action="{{ route('drivers.documents.store', $driver) }}" method="POST" enctype="multipart/form-data" class="space-y-4 p-6" id="driverDocumentForm">
                                        @csrf
                                        @if ($errors->any())
                                            <div class="rounded-2xl bg-rose-50 p-4 text-xs font-bold text-rose-700">
                                                <ul class="list-disc pl-4 space-y-1">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                        <input type="hidden" name="redirect_tab" value="documents">
                                        <input type="hidden" id="driverBirthDate" value="{{ optional($driver->birth_date)->format('Y-m-d') }}">

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Belge Türü</label>
                                            <select name="document_type" id="document_type"
                                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                                                @foreach($driverDocumentTypes as $type)
                                                    <option value="{{ $type }}">{{ $type }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-2 block text-sm font-semibold text-slate-700">Başlangıç</label>
                                                <input type="date" name="start_date" id="start_date"
                                                       class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                                            </div>

                                            <div>
                                                <label class="mb-2 block text-sm font-semibold text-slate-700">Bitiş</label>
                                                <input type="date" name="end_date" id="end_date"
                                                       class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600" id="document_auto_info">
                                            Belge türüne göre bitiş tarihi otomatik hesaplanabilir.
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Dosya</label>
                                            <input type="file" name="file"
                                                   class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Not</label>
                                            <textarea name="notes" rows="3"
                                                      class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm"></textarea>
                                        </div>

                                        <button type="submit"
                                                class="w-full rounded-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-4 text-sm font-semibold text-white shadow transition hover:scale-[1.01]">
                                            Belgeyi Yükle
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        @if($activeTab === 'payrolls')
                            <div class="grid gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5">
                                        <h4 class="text-xl font-bold text-slate-900">Maaş Kayıtları</h4>
                                        <p class="mt-1 text-sm text-slate-500">Personelin bordro ve maaş geçmişi</p>
                                    </div>

                                    @if($driver->payrolls->count())
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead class="bg-slate-50 text-slate-500">
                                                    <tr>
                                                        <th class="px-6 py-4 text-left font-bold">Dönem</th>
                                                        <th class="px-6 py-4 text-left font-bold">Brüt Maaş</th>
                                                        <th class="px-6 py-4 text-left font-bold">Net Maaş</th>
                                                        <th class="px-6 py-4 text-left font-bold">Durum</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($driver->payrolls as $payroll)
                                                        <tr class="hover:bg-slate-50/70">
                                                            <td class="px-6 py-5 font-semibold text-slate-900">
                                                                {{ $payroll->period_month ? \Carbon\Carbon::parse($payroll->period_month)->format('m.Y') : '-' }}
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                {{ !is_null($payroll->gross_salary) ? number_format((float) $payroll->gross_salary, 2, ',', '.') . ' ₺' : '-' }}
                                                            </td>
                                                            <td class="px-6 py-5 font-semibold text-slate-900">
                                                                {{ !is_null($payroll->net_salary) ? number_format((float) $payroll->net_salary, 2, ',', '.') . ' ₺' : '-' }}
                                                            </td>
                                                            <td class="px-6 py-5">
                                                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">
                                                                    Kayıtlı
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="p-8 text-center text-sm text-slate-500">Henüz maaş kaydı yok.</div>
                                    @endif
                                </div>

                                <div class="xl:col-span-4 space-y-6">
                                    <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                        <div class="border-b border-slate-100 px-6 py-5">
                                            <h4 class="text-xl font-bold text-slate-900">Maaş Özeti</h4>
                                            <p class="mt-1 text-sm text-slate-500">Toplam bordro görünümü</p>
                                        </div>

                                        <div class="space-y-4 p-6">
                                            <div class="rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 p-5 text-white">
                                                <div class="text-sm text-white/80">Toplam Net Maaş</div>
                                                <div class="mt-2 text-2xl font-extrabold">{{ number_format($totalNetSalary, 2, ',', '.') }} ₺</div>
                                            </div>

                                            <div class="rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-500 p-5 text-white">
                                                <div class="text-sm text-white/80">Toplam Brüt Maaş</div>
                                                <div class="mt-2 text-2xl font-extrabold">{{ number_format($totalGrossSalary, 2, ',', '.') }} ₺</div>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Son Bordro</div>
                                                <div class="mt-2 text-base font-bold text-slate-900">
                                                    {{ $lastPayroll?->period_month ? \Carbon\Carbon::parse($lastPayroll->period_month)->format('m.Y') : '-' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($activeTab === 'images')
                            <div class="grid gap-6 xl:grid-cols-12">
                                <div class="xl:col-span-8 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5">
                                        <h4 class="text-xl font-bold text-slate-900">Personel Resimleri</h4>
                                        <p class="mt-1 text-sm text-slate-500">Vesikalık, profil ve diğer görseller</p>
                                    </div>

                                    @if($imageDocuments->count())
                                        <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
                                            @foreach($imageDocuments as $image)
                                                <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                                                    <div class="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden">
                                                        @php
                                                            $extension = strtolower(pathinfo((string) $image->file_path, PATHINFO_EXTENSION));
                                                        @endphp
                                                        @if($extension === 'pdf')
                                                            <div class="flex flex-col items-center gap-2">
                                                                <svg class="w-12 h-12 text-rose-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9v-2h2v2zm0-4H9V7h2v5z"/></svg>
                                                                <span class="text-[10px] font-bold text-slate-400 uppercase">PDF Belgesi</span>
                                                            </div>
                                                        @else
                                                            <img src="/storage/{{ $image->file_path }}"
                                                                 alt="{{ $image->document_name }}"
                                                                 class="h-full w-full object-cover">
                                                        @endif
                                                    </div>

                                                    <div class="p-4">
                                                        <div class="text-sm font-semibold text-slate-900">{{ $image->document_name ?: 'Personel Resmi' }}</div>
                                                        <div class="mt-1 text-xs text-slate-500">{{ $image->document_type ?: '-' }}</div>

                                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                                            <a href="/storage/{{ $image->file_path }}"
                                                               target="_blank"
                                                               class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                                                                Görüntüle
                                                            </a>

                                                            <button type="button"
                                                                    onclick="openCropModal('/storage/{{ $image->file_path }}')"
                                                                    class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                                                Kırp ve Profil Yap
                                                            </button>

                                                            <form action="{{ route('drivers.documents.destroy', [$driver, $image]) }}" method="POST" onsubmit="return confirm('Bu resmi silmek istediğine emin misin?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                        class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                                    Sil
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="p-8 text-center text-sm text-slate-500">Henüz resim yüklenmemiş.</div>
                                    @endif
                                </div>

                                <div class="xl:col-span-4 rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="border-b border-slate-100 px-6 py-5">
                                        <h4 class="text-xl font-bold text-slate-900">Yeni Resim Yükle</h4>
                                        <p class="mt-1 text-sm text-slate-500">Personel resmi veya belge görseli ekleyin</p>
                                    </div>

                                    <form action="{{ route('drivers.documents.store', $driver) }}" method="POST" enctype="multipart/form-data" class="space-y-4 p-6">
                                        @csrf
                                        @if ($errors->any())
                                            <div class="rounded-2xl bg-rose-50 p-4 text-xs font-bold text-rose-700">
                                                <ul class="list-disc pl-4 space-y-1">
                                                    @foreach ($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                        <input type="hidden" name="redirect_tab" value="images">

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Belge Türü</label>
                                            <select name="document_type" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                                                <option value="Kimlik ve Ehliyet">Personel Resmi</option>
                                                <option value="Kimlik ve Ehliyet">Vesikalık</option>
                                                <option value="Kimlik ve Ehliyet">Profil Fotoğrafı</option>
                                                <option value="Kimlik ve Ehliyet">Diğer Resim</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Resim Dosyası</label>
                                            <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp"
                                                   class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Not</label>
                                            <textarea name="notes" rows="3"
                                                      class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm"></textarea>
                                        </div>

                                        <button type="submit"
                                                class="w-full rounded-2xl bg-gradient-to-r from-violet-500 to-fuchsia-500 px-5 py-4 text-sm font-semibold text-white shadow transition hover:scale-[1.01]">
                                            Resmi Yükle
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        @if($activeTab === 'leaves')
                            @include('drivers.partials.leaves-tab')
                        @endif
                    </div>
                </div>
            </div>

            </div>
        </div>
    </div>

    <!-- İşten Ayrılma Modalı -->
    <div id="leaveModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-[32px] bg-white p-8 shadow-2xl">
            <h3 class="text-2xl font-black text-slate-900">🛑 İşten Ayrılma Kaydı</h3>
            <p class="mt-2 text-sm text-slate-500 font-medium">Şoförün ayrılış tarihini ve son vardiyasını seçin.</p>
            
            <form action="{{ route('drivers.leave-work', $driver) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-400">Ayrılma Tarihi</label>
                    <input type="date" name="leave_date" value="{{ date('Y-m-d') }}" required
                           class="w-full rounded-2xl border-slate-200 bg-slate-50 py-3 px-4 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-400">Son Yapılan Sefer</label>
                    <select name="leave_shift" required
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 py-3 px-4 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-blue-500">
                        <option value="morning">Sabah Seferini Yaptı Bıraktı</option>
                        <option value="evening">Akşam Seferini Yaptı Bıraktı</option>
                        <option value="full_day">Tüm Gün Seferlerini Yaptı Bıraktı</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeLeaveModal()" class="flex-1 rounded-2xl border border-slate-200 py-3 text-sm font-bold text-slate-600">Vazgeç</button>
                    <button type="submit" class="flex-1 rounded-2xl bg-rose-600 py-3 text-sm font-bold text-white shadow-lg shadow-rose-200">Kaydı Onayla</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Araç Değiştirme Modalı -->
    <div id="changeVehicleModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-[32px] bg-white p-8 shadow-2xl">
            <h3 class="text-2xl font-black text-slate-900">🚐 Araç Değiştir / Ata</h3>
            <p class="mt-2 text-sm text-slate-500 font-medium">Şoförü atamak istediğiniz aracı ve zimmet başlangıç tarihini seçin.</p>
            
            <form action="{{ route('drivers.change-vehicle', $driver) }}" method="POST" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-400">Yeni Araç Seçin</label>
                    <select name="vehicle_id" required
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 py-3 px-4 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Araç Seçiniz...</option>
                        @foreach(\App\Models\Fleet\Vehicle::orderBy('plate')->get() as $v)
                            <option value="{{ $v->id }}" {{ $driver->vehicle_id == $v->id ? 'selected' : '' }}>
                                {{ $v->plate }} ({{ $v->brand }} {{ $v->model }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-400">Zimmet Başlangıç Tarihi</label>
                    <input type="date" name="assignment_date" value="{{ date('Y-m-d') }}" required
                           class="w-full rounded-2xl border-slate-200 bg-slate-50 py-3 px-4 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-slate-400">Bu tarihten itibaren yeni araç atanacak. Eski zimmet otomatik kapanır.</p>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-widest text-slate-400">Başlangıç Vardiyası</label>
                    <select name="assignment_shift" required
                            class="w-full rounded-2xl border-slate-200 bg-slate-50 py-3 px-4 text-sm font-bold text-slate-900 focus:border-blue-500 focus:ring-blue-500">
                        <option value="morning">Sabah Seferinden İtibaren</option>
                        <option value="evening">Akşam Seferinden İtibaren</option>
                        <option value="full_day">Tüm Gün</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeChangeVehicleModal()" class="flex-1 rounded-2xl border border-slate-200 py-3 text-sm font-bold text-slate-600">Vazgeç</button>
                    <button type="submit" class="flex-1 rounded-2xl bg-blue-600 py-3 text-sm font-bold text-white shadow-lg shadow-blue-200">Atamayı Güncelle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Crop Modal -->
    <div id="cropModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-2xl rounded-[30px] bg-white shadow-2xl overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-5 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-extrabold text-slate-900">Resmi Kırp</h3>
                    <p class="mt-1 text-sm text-slate-500">Personel profil resmini seçin (1:1 oranında)</p>
                </div>
                <button type="button" onclick="closeCropModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="max-h-[50vh] overflow-hidden rounded-2xl bg-slate-100 flex items-center justify-center">
                    <img id="cropImage" src="" alt="Kırpılacak Resim" class="max-w-full">
                </div>
            </div>
            <div class="border-t border-slate-100 px-6 py-4 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeCropModal()" class="rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">Vazgeç</button>
                <button type="button" onclick="saveCroppedImage()" id="saveCropBtn" class="rounded-2xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 transition flex items-center gap-2">
                    <span id="saveCropBtnText">Kırp ve Profil Resmi Yap</span>
                    <div id="saveCropSpinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></div>
                </button>
            </div>
        </div>
    </div>

    <script>
        function openLeaveModal() { document.getElementById('leaveModal').classList.replace('hidden', 'flex'); }
        function closeLeaveModal() { document.getElementById('leaveModal').classList.replace('flex', 'hidden'); }
        
        function openChangeVehicleModal() { document.getElementById('changeVehicleModal').classList.replace('hidden', 'flex'); }
        function closeChangeVehicleModal() { document.getElementById('changeVehicleModal').classList.replace('flex', 'hidden'); }

        let cropper = null;
        const cropModal = document.getElementById('cropModal');
        const cropImage = document.getElementById('cropImage');
        const saveCropBtn = document.getElementById('saveCropBtn');
        const saveCropBtnText = document.getElementById('saveCropBtnText');
        const saveCropSpinner = document.getElementById('saveCropSpinner');

        function openCropModal(fileUrl) {
            const extension = fileUrl.split('.').pop().toLowerCase();
            
            cropModal.classList.remove('hidden');
            cropModal.classList.add('flex');
            
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            if (extension === 'pdf') {
                // PDF İşleme
                saveCropBtn.disabled = true;
                saveCropBtnText.innerText = 'PDF Hazırlanıyor...';
                saveCropSpinner.classList.remove('hidden');
                cropImage.style.display = 'none';

                const loadingTask = pdfjsLib.getDocument(fileUrl);
                loadingTask.promise.then(pdf => {
                    pdf.getPage(1).then(page => {
                        const viewport = page.getViewport({ scale: 2.0 }); // Daha net görüntü için 2x scale
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const renderTask = page.render({
                            canvasContext: context,
                            viewport: viewport
                        });

                        renderTask.promise.then(() => {
                            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                            initCropper(dataUrl);
                            
                            saveCropBtn.disabled = false;
                            saveCropBtnText.innerText = 'Kırp ve Profil Resmi Yap';
                            saveCropSpinner.classList.add('hidden');
                            cropImage.style.display = 'block';
                        });
                    });
                }).catch(err => {
                    console.error('PDF Render Hatası:', err);
                    alert('PDF dosyası açılamadı.');
                    closeCropModal();
                });
            } else {
                initCropper(fileUrl);
            }
        }

        function initCropper(imageSrc) {
            cropImage.src = imageSrc;
            
            if (cropper) {
                cropper.destroy();
            }

            setTimeout(() => {
                cropper = new Cropper(cropImage, {
                    aspectRatio: null,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            }, 100);
        }

        function closeCropModal() {
            cropModal.classList.add('hidden');
            cropModal.classList.remove('flex');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function saveCroppedImage() {
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
            });

            const base64Image = canvas.toDataURL('image/jpeg', 0.9);

            saveCropBtn.disabled = true;
            saveCropBtnText.innerText = 'İşleniyor...';
            saveCropSpinner.classList.remove('hidden');

            fetch("{{ route('drivers.crop-photo', $driver) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    image: base64Image
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || window.location.href;
                } else {
                    alert(data.message || 'Bir hata oluştu.');
                    saveCropBtn.disabled = false;
                    saveCropBtnText.innerText = 'Kırp ve Profil Resmi Yap';
                    saveCropSpinner.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Sistem hatası oluştu.');
                saveCropBtn.disabled = false;
                saveCropBtnText.innerText = 'Kırp ve Profil Resmi Yap';
                saveCropSpinner.classList.add('hidden');
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // ... mevcut script içeriği ...
            const typeEl = document.getElementById('document_type');
            const startEl = document.getElementById('start_date');
            const endEl = document.getElementById('end_date');
            const infoEl = document.getElementById('document_auto_info');
            const birthDateEl = document.getElementById('driverBirthDate');

            if (!typeEl || !startEl || !endEl || !infoEl) {
                return;
            }

            function addMonths(date, months) {
                const d = new Date(date);
                const day = d.getDate();
                d.setMonth(d.getMonth() + months);

                if (d.getDate() < day) {
                    d.setDate(0);
                }

                return d;
            }

            function addYears(date, years) {
                const d = new Date(date);
                d.setFullYear(d.getFullYear() + years);
                return d;
            }

            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            function updateEndDate() {
                const type = typeEl.value;
                const startValue = startEl.value;
                const birthValue = birthDateEl ? birthDateEl.value : '';

                infoEl.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600';
                infoEl.textContent = 'Belge türüne göre bitiş tarihi otomatik hesaplanabilir.';

                if (type === 'SRC 1 Belgesi' || type === 'SRC 2 Belgesi') {
                    if (birthValue) {
                        const birthDate = new Date(birthValue);
                        const srcEnd = addYears(birthDate, 69);
                        endEl.value = formatDate(srcEnd);
                        endEl.readOnly = true;
                        infoEl.className = 'rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-700';
                        infoEl.textContent = 'SRC belgesi bitiş tarihi, personelin doğum tarihine göre 69 yaş kuralıyla otomatik hesaplandı.';
                    } else {
                        endEl.value = '';
                        endEl.readOnly = false;
                        infoEl.className = 'rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700';
                        infoEl.textContent = 'SRC bitiş tarihi için personelin doğum tarihi gerekli.';
                    }
                    return;
                }

                if ((type === 'Psikoteknik Belgesi' || type === 'MYK Belgesi') && startValue) {
                    const startDate = new Date(startValue);
                    const endDate = addYears(startDate, 5);
                    endEl.value = formatDate(endDate);
                    endEl.readOnly = true;
                    infoEl.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700';
                    infoEl.textContent = 'Bu belge için bitiş tarihi başlangıç tarihinden 5 yıl sonrası olarak otomatik hesaplandı.';
                    return;
                }

                if (type === 'Adli Sicil Kaydı' && startValue) {
                    const startDate = new Date(startValue);
                    const endDate = addMonths(startDate, 6);
                    endEl.value = formatDate(endDate);
                    endEl.readOnly = true;
                    infoEl.className = 'rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3 text-xs text-orange-700';
                    infoEl.textContent = 'Adli Sicil Kaydı için bitiş tarihi başlangıç tarihinden 6 ay sonrası olarak otomatik hesaplandı.';
                    return;
                }

                endEl.readOnly = false;
            }

            typeEl.addEventListener('change', updateEndDate);
            startEl.addEventListener('change', updateEndDate);

            updateEndDate();
        });
    </script>
@endsection
