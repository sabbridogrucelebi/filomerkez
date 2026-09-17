@extends('layouts.app')

@section('title', 'Petrol İstasyonları')
@section('subtitle', 'Cari hesap ve ödeme yönetimi')

@section('content')
@php
    $stations = collect($stations ?? []);

    $paymentMethodMap = [
        'nakit' => 'Nakit',
        'havale' => 'Havale',
        'eft' => 'EFT',
        'kredi_karti' => 'Kredi Kartı',
        'cek' => 'Çek',
        'diger' => 'Diğer',
    ];
@endphp

<div
    class="space-y-6"
    x-data="{
        openCreate: false,
        openPayment: false,
        openBulkPayment: false,
        activeDetail: null,
        paymentMode: 'create',
        paymentFormAction: '{{ route('fuel-stations.payments.store') }}',
        paymentFormMethod: 'POST',
        paymentFormTitle: 'Ödeme Gir',
        paymentFormSubtitle: 'İstasyona yapılan ödemeyi sisteme işle',
        paymentForm: {
            id: null,
            fuel_station_id: '',
            payment_date: '{{ now()->format('Y-m-d') }}',
            start_date: '',
            end_date: '',
            amount: '',
            payment_method: 'nakit',
            notes: ''
        },
        
        calculatedDebt: {
            isLoading: false,
            show: false,
            totalLiters: 0,
            grossTotal: 0,
            discountTotal: 0,
            vatTotal: 0,
            netTotal: 0,
            paidTotal: 0,
            netPayable: 0
        },

        init() {
            this.$watch('paymentForm.fuel_station_id', () => this.calculateDebt());
            this.$watch('paymentForm.start_date', () => this.calculateDebt());
            this.$watch('paymentForm.end_date', () => this.calculateDebt());
        },

        async calculateDebt() {
            if (!this.paymentForm.fuel_station_id || !this.paymentForm.start_date || !this.paymentForm.end_date) {
                this.calculatedDebt.show = false;
                return;
            }
            
            if (this.paymentForm.start_date > this.paymentForm.end_date) {
                this.calculatedDebt.show = false;
                return;
            }

            this.calculatedDebt.isLoading = true;
            this.calculatedDebt.show = true;
            
            try {
                const baseUrl = '{{ route('fuel-stations.calculate-debt') }}';
                const response = await fetch(`${baseUrl}?fuel_station_id=${this.paymentForm.fuel_station_id}&start_date=${this.paymentForm.start_date}&end_date=${this.paymentForm.end_date}`);
                if (!response.ok) throw new Error('API Error');
                
                const data = await response.json();
                this.calculatedDebt.totalLiters = data.total_liters;
                this.calculatedDebt.grossTotal = data.gross_total;
                this.calculatedDebt.discountTotal = data.discount_total;
                this.calculatedDebt.vatTotal = data.vat_total;
                this.calculatedDebt.netTotal = data.net_total;
                this.calculatedDebt.paidTotal = data.paid_total;
                this.calculatedDebt.netPayable = data.net_payable;
                
                // Automatically set amount to net payable if empty or unchanged from previous calc
                if (!this.paymentForm.amount || this.paymentMode === 'create') {
                    this.paymentForm.amount = data.net_payable.toFixed(2);
                }
            } catch (error) {
                console.error('Borç hesaplanırken hata oluştu:', error);
                this.calculatedDebt.show = false;
            } finally {
                this.calculatedDebt.isLoading = false;
            }
        },

        resetPaymentForm() {
            this.paymentMode = 'create';
            this.paymentFormAction = '{{ route('fuel-stations.payments.store') }}';
            this.paymentFormMethod = 'POST';
            this.paymentFormTitle = 'Ödeme Gir';
            this.paymentFormSubtitle = 'İstasyona yapılan ödemeyi sisteme işle';
            this.paymentForm = {
                id: null,
                fuel_station_id: '',
                payment_date: '{{ now()->format('Y-m-d') }}',
                start_date: '',
                end_date: '',
                amount: '',
                payment_method: 'nakit',
                notes: ''
            };
            this.calculatedDebt.show = false;
        },

        openCreatePaymentModal(stationId = '') {
            this.resetPaymentForm();
            this.paymentForm.fuel_station_id = stationId ? String(stationId) : '';
            this.openPayment = true;
        },

        async editPayment(paymentId) {
            const response = await fetch(`{{ url('/fuel-stations/payments') }}/${paymentId}`);
            const data = await response.json();

            this.paymentMode = 'edit';
            this.paymentFormAction = `/fuel-stations/payments/${paymentId}`;
            this.paymentFormMethod = 'PUT';
            this.paymentFormTitle = 'Ödeme Düzenle';
            this.paymentFormSubtitle = 'Kayıtlı ödeme bilgisini güncelle';
            this.paymentForm = {
                id: data.id,
                fuel_station_id: data.fuel_station_id ? String(data.fuel_station_id) : '',
                payment_date: data.payment_date || '',
                start_date: data.start_date || '',
                end_date: data.end_date || '',
                amount: data.amount ?? '',
                payment_method: data.payment_method || 'nakit',
                notes: data.notes || ''
            };

            this.openPayment = true;
        }
    }"
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
        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-emerald-50/30 px-6 py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-[20px] bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-200/70">
                        ⛽
                    </div>

                    <div>
                        <h2 class="text-[26px] font-bold tracking-tight text-slate-900">Petrol İstasyonları</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Cari hesap, iskonto, ödeme ve anlık borç takibi
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('activity-logs.index', ['module' => 'fuel_station_payment']) }}"
                       class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Ödeme Kayıtları
                    </a>

                    @if(auth()->user()->hasPermission('fuels.create'))
                    <button
                        type="button"
                        @click="openBulkPayment = true"
                        class="inline-flex items-center rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-100"
                    >
                        Toplu Ödeme
                    </button>

                    <button
                        type="button"
                        @click="openCreate = true"
                        class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    >
                        Cari Aç
                    </button>

                    <button
                        type="button"
                        @click="openCreatePaymentModal()"
                        class="inline-flex items-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200/60 transition hover:scale-[1.01]"
                    >
                        Ödeme Gir
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6">
            @if($stations->count())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1600px]">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Petrol Adı</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Ünvan</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Adres</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İskonto</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Toplam Litre</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Brüt Tutar</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İskonto Toplamı</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">KDV Toplamı</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Net Borç</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Toplam Ödeme</th>
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Anlık Cari Borç</th>
                                <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İşlem</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @foreach($stations as $station)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-bold text-slate-800">{{ $station->name }}</div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ $station->legal_name ?: '-' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600 max-w-[260px]">
                                        <div class="line-clamp-2">{{ $station->address ?: '-' }}</div>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        @if($station->discount_type === 'percentage')
                                            %{{ number_format((float) $station->discount_value, 2, ',', '.') }}
                                        @elseif($station->discount_type === 'fixed')
                                            {{ number_format((float) $station->discount_value, 2, ',', '.') }} ₺
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ number_format((float) $station->summary->total_liters, 2, ',', '.') }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ number_format((float) $station->summary->gross_total, 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ number_format((float) $station->summary->discount_total, 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ number_format((float) $station->summary->vat_total, 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-6 py-4 text-sm font-semibold text-slate-700">
                                        {{ number_format((float) $station->summary->net_total, 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-6 py-4 text-sm {{ (float) $station->summary->total_paid > 0 ? 'font-bold text-emerald-700' : 'text-slate-600' }}">
                                        {{ number_format((float) $station->summary->total_paid, 2, ',', '.') }} ₺
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ (float) $station->summary->current_debt > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ number_format((float) $station->summary->current_debt, 2, ',', '.') }} ₺
                                        </span>
                                    </td>

                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('fuel-stations.statement', $station) }}"
                                               class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">
                                                Ekstre
                                            </a>

                                            <button
                                                type="button"
                                                @click="activeDetail = activeDetail === {{ $station->id }} ? null : {{ $station->id }}"
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                            >
                                                Detay
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr x-show="activeDetail === {{ $station->id }}" x-transition style="display:none;" class="bg-slate-50/60">
                                    <td colspan="11" class="px-6 py-6">
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                                            <div class="xl:col-span-1">
                                                <div class="rounded-[24px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-slate-100 px-5 py-4">
                                                        <h4 class="text-base font-bold text-slate-800">Cari Özet</h4>
                                                        <p class="mt-1 text-xs text-slate-500">İstasyona ait toplu mali durum</p>
                                                    </div>

                                                    <div class="space-y-3 p-5">
                                                        <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                                            <span class="text-sm text-slate-500">Toplam Litre</span>
                                                            <span class="font-bold text-slate-800">{{ number_format((float) $station->summary->total_liters, 2, ',', '.') }}</span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                                            <span class="text-sm text-slate-500">Brüt Tutar</span>
                                                            <span class="font-bold text-slate-800">{{ number_format((float) $station->summary->gross_total, 2, ',', '.') }} ₺</span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                                            <span class="text-sm text-slate-500">İskonto Toplamı</span>
                                                            <span class="font-bold text-slate-800">{{ number_format((float) $station->summary->discount_total, 2, ',', '.') }} ₺</span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                                                            <span class="text-sm text-slate-500">KDV Toplamı</span>
                                                            <span class="font-bold text-slate-800">{{ number_format((float) $station->summary->vat_total, 2, ',', '.') }} ₺</span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                                            <span class="text-sm text-emerald-700">Toplam Ödeme</span>
                                                            <span class="font-bold text-emerald-700">{{ number_format((float) $station->summary->total_paid, 2, ',', '.') }} ₺</span>
                                                        </div>

                                                        <div class="flex items-center justify-between rounded-2xl border {{ (float) $station->summary->current_debt > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} px-4 py-3">
                                                            <span class="text-sm {{ (float) $station->summary->current_debt > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Anlık Cari Borç</span>
                                                            <span class="font-bold {{ (float) $station->summary->current_debt > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                                                {{ number_format((float) $station->summary->current_debt, 2, ',', '.') }} ₺
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="xl:col-span-2">
                                                <div class="rounded-[24px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                                                    <div class="border-b border-slate-100 bg-gradient-to-r from-blue-50 via-white to-slate-50 px-5 py-4">
                                                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                                            <div>
                                                                <h4 class="text-base font-bold text-slate-800">Ödeme Geçmişi</h4>
                                                                <p class="mt-1 text-xs text-slate-500">Tarih, yöntem ve dönem bazlı istasyon ödeme detayları</p>
                                                            </div>

                                                            @if(auth()->user()->hasPermission('fuels.create'))
                                                            <button
                                                                type="button"
                                                                @click="openCreatePaymentModal('{{ $station->id }}')"
                                                                class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md transition hover:scale-[1.01]"
                                                            >
                                                                + Bu istasyona ödeme gir
                                                            </button>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if($station->payments->count())
                                                        <div class="overflow-x-auto">
                                                            <table class="w-full min-w-[1100px]">
                                                                <thead class="bg-slate-50 border-b border-slate-100">
                                                                    <tr>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Ödeme Tarihi</th>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Yöntem</th>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Başlangıç</th>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Bitiş</th>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Ödeme Tutarı</th>
                                                                        <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Not</th>
                                                                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.12em] text-slate-500">İşlem</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-slate-100">
                                                                    @foreach($station->payments as $payment)
                                                                        <tr class="hover:bg-slate-50/70 transition">
                                                                            <td class="px-4 py-4 text-sm text-slate-700">
                                                                                {{ optional($payment->payment_date)->format('d.m.Y') ?: '-' }}
                                                                            </td>

                                                                            <td class="px-4 py-4">
                                                                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                                                                    {{ $paymentMethodMap[$payment->payment_method] ?? '-' }}
                                                                                </span>
                                                                            </td>

                                                                            <td class="px-4 py-4 text-sm text-slate-600">
                                                                                {{ optional($payment->start_date)->format('d.m.Y') ?: '-' }}
                                                                            </td>

                                                                            <td class="px-4 py-4 text-sm text-slate-600">
                                                                                {{ optional($payment->end_date)->format('d.m.Y') ?: '-' }}
                                                                            </td>

                                                                            <td class="px-4 py-4 text-sm font-bold text-emerald-700">
                                                                                {{ number_format((float) $payment->amount, 2, ',', '.') }} ₺
                                                                            </td>

                                                                            <td class="px-4 py-4 text-sm text-slate-500">
                                                                                {{ $payment->notes ?: '-' }}
                                                                            </td>

                                                                            <td class="px-4 py-4">
                                                                                <div class="flex items-center justify-end gap-2">
                                                                                    @if(auth()->user()->hasPermission('fuels.edit'))
                                                                                    <button
                                                                                        type="button"
                                                                                        @click="editPayment('{{ $payment->id }}')"
                                                                                        class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100"
                                                                                    >
                                                                                        Düzenle
                                                                                    </button>
                                                                                    @endif

                                                                                    @if(auth()->user()->hasPermission('fuels.delete'))
                                                                                    <form action="{{ route('fuel-stations.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Bu ödeme kaydını silmek istediğine emin misin?')">
                                                                                        @csrf
                                                                                        @method('DELETE')
                                                                                        <button
                                                                                            type="submit"
                                                                                            class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                                                                        >
                                                                                            Sil
                                                                                        </button>
                                                                                    </form>
                                                                                    @endif
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @else
                                                        <div class="p-8 text-center text-sm text-slate-500">
                                                            Bu istasyon için henüz ödeme kaydı yok.
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
                    Henüz petrol istasyonu cari kaydı yok.
                </div>
            @endif
        </div>
    </div>

    <template x-teleport="body">
        <div x-cloak x-show="openCreate" x-transition.opacity class="fixed inset-0 z-[9999]" style="display:none;">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[3px]" @click="openCreate = false"></div>

            <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
                <div
                    x-show="openCreate"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="w-full max-w-4xl overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-[0_40px_120px_rgba(15,23,42,0.28)]"
                    @click.stop
                >
                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-emerald-50/40 px-6 py-5 sm:px-7">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] bg-gradient-to-br from-emerald-500 to-teal-500 text-xl text-white shadow-lg shadow-emerald-200/70">
                                    🧾
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-slate-900">Cari Aç</h3>
                                    <p class="mt-1 text-sm text-slate-500">Petrol istasyonu bilgilerini ve iskonto yapısını kaydet</p>
                                </div>
                            </div>

                            <button type="button" @click="openCreate = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                ✕
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('fuel-stations.store') }}" method="POST">
                        @csrf

                        <div class="max-h-[70vh] overflow-y-auto px-6 py-6 sm:px-7">
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Petrol Adı</label>
                                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Ünvan</label>
                                    <input type="text" name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Adres</label>
                                    <textarea name="address" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('address') }}</textarea>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">İskonto Türü</label>
                                    <select name="discount_type" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                        <option value="">Yok</option>
                                        <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>Yüzde (%)</option>
                                        <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Sabit Tutar (₺)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">İskonto Tutarı / Oranı</label>
                                    <input type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', 0) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">KDV Oranı (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="vat_rate" value="{{ old('vat_rate', 0) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:px-7">
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button type="button" @click="openCreate = false" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Vazgeç
                                </button>

                                <button type="submit" class="rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-200/60 transition hover:scale-[1.01]">
                                    Cariyi Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-cloak x-show="openPayment" x-transition.opacity class="fixed inset-0 z-[9999]" style="display:none;">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[3px]" @click="openPayment = false"></div>

            <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
                <div
                    x-show="openPayment"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="w-full max-w-4xl overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-[0_40px_120px_rgba(15,23,42,0.28)]"
                    @click.stop
                >
                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-blue-50/40 px-6 py-5 sm:px-7">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] bg-gradient-to-br from-blue-600 to-indigo-600 text-xl text-white shadow-lg shadow-blue-200/70">
                                    💳
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-slate-900" x-text="paymentFormTitle"></h3>
                                    <p class="mt-1 text-sm text-slate-500" x-text="paymentFormSubtitle"></p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="openPayment = false; resetPaymentForm();"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    <form :action="paymentFormAction" method="POST">
                        @csrf
                        <input type="hidden" name="_method" :value="paymentFormMethod">

                        <div class="max-h-[70vh] overflow-y-auto px-6 py-6 sm:px-7">
                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Petrol İstasyonu</label>
                                    <select name="fuel_station_id" x-model="paymentForm.fuel_station_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                        <option value="">İstasyon seçiniz</option>
                                        @foreach($stations as $station)
                                            <option value="{{ $station->id }}">{{ $station->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tarihi</label>
                                    <input type="date" name="payment_date" x-model="paymentForm.payment_date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Başlangıç Tarihi</label>
                                    <input type="date" name="start_date" x-model="paymentForm.start_date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Bitiş Tarihi</label>
                                    <input type="date" name="end_date" x-model="paymentForm.end_date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                </div>

                                <!-- Hesaplanan Borç Özeti -->
                                <div class="md:col-span-2" x-show="calculatedDebt.show" x-transition x-cloak>
                                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm relative overflow-hidden">
                                        <div x-show="calculatedDebt.isLoading" class="absolute inset-0 bg-white/70 backdrop-blur-[2px] flex items-center justify-center z-10">
                                            <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-blue-600"></div>
                                        </div>

                                        <div class="bg-slate-50 px-4 py-3 border-b border-slate-100">
                                            <h4 class="text-sm font-bold text-slate-800">Seçili Tarihler Arası Borç Özeti</h4>
                                        </div>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-sm whitespace-nowrap">
                                                <thead>
                                                    <tr class="bg-slate-50/50 text-slate-500">
                                                        <th class="px-4 py-2 font-medium">Toplam Alınan (Litre)</th>
                                                        <th class="px-4 py-2 font-medium">İskontosuz Brüt Tutar</th>
                                                        <th class="px-4 py-2 font-medium">İskonto</th>
                                                        <th class="px-4 py-2 font-medium">KDV Tutarı</th>
                                                        <th class="px-4 py-2 font-medium text-emerald-600">Ödenen Toplam</th>
                                                        <th class="px-4 py-2 font-bold text-blue-600">Net Kalan Borç</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    <tr>
                                                        <td class="px-4 py-3 font-semibold text-slate-700" x-text="`${calculatedDebt.totalLiters.toLocaleString('tr-TR', {minimumFractionDigits: 2})} LT`"></td>
                                                        <td class="px-4 py-3 font-semibold text-slate-700" x-text="`${calculatedDebt.grossTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`"></td>
                                                        <td class="px-4 py-3 font-semibold text-slate-700" x-text="`${calculatedDebt.discountTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`"></td>
                                                        <td class="px-4 py-3 font-semibold text-slate-700" x-text="`${calculatedDebt.vatTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`"></td>
                                                        <td class="px-4 py-3 font-bold text-emerald-700" x-text="`${calculatedDebt.paidTotal.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`"></td>
                                                        <td class="px-4 py-3 font-bold text-blue-700 text-base" x-text="`${calculatedDebt.netPayable.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Tutarı</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" x-model="paymentForm.amount" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Ödeme Yöntemi</label>
                                    <select name="payment_method" x-model="paymentForm.payment_method" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                                        <option value="nakit">Nakit</option>
                                        <option value="havale">Havale</option>
                                        <option value="eft">EFT</option>
                                        <option value="kredi_karti">Kredi Kartı</option>
                                        <option value="cek">Çek</option>
                                        <option value="diger">Diğer</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Not</label>
                                    <textarea name="notes" rows="3" x-model="paymentForm.notes" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 bg-slate-50/70 px-6 py-4 sm:px-7">
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    @click="openPayment = false; resetPaymentForm();"
                                    class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                >
                                    Vazgeç
                                </button>

                                <button type="submit" class="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200/60 transition hover:scale-[1.01]">
                                    <span x-text="paymentMode === 'edit' ? 'Ödemeyi Güncelle' : 'Ödemeyi Kaydet'"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>

    <template x-teleport="body">
        <div x-cloak x-show="openBulkPayment" x-transition.opacity class="fixed inset-0 z-[9999]" style="display:none;">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[3px]" @click="openBulkPayment = false"></div>

            <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
                <div
                    x-show="openBulkPayment"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                    class="w-full max-w-5xl overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-[0_40px_120px_rgba(15,23,42,0.28)]"
                    @click.stop
                >
                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-blue-50/40 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Toplu Ödeme İşle</h3>
                                <p class="mt-1 text-sm text-slate-500">Birden fazla istasyon ödemesini tek seferde kaydet</p>
                            </div>

                            <button type="button"
                                    @click="openBulkPayment = false"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                ✕
                            </button>
                        </div>
                    </div>

                    <form action="{{ route('fuel-stations.payments.bulk') }}" method="POST" class="p-6 space-y-4">
                        @csrf

                        @for($i = 0; $i < 3; $i++)
                            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-5">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">İstasyon</label>
                                    <select name="payments[{{ $i }}][fuel_station_id]" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                        <option value="">Seçiniz</option>
                                        @foreach($stations as $station)
                                            <option value="{{ $station->id }}">{{ $station->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Tarih</label>
                                    <input type="date" name="payments[{{ $i }}][payment_date]" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Tutar</label>
                                    <input type="number" step="0.01" min="0.01" name="payments[{{ $i }}][amount]" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Yöntem</label>
                                    <select name="payments[{{ $i }}][payment_method]" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                        <option value="nakit">Nakit</option>
                                        <option value="havale">Havale</option>
                                        <option value="eft">EFT</option>
                                        <option value="kredi_karti">Kredi Kartı</option>
                                        <option value="cek">Çek</option>
                                        <option value="diger">Diğer</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Not</label>
                                    <input type="text" name="payments[{{ $i }}][notes]" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                </div>
                            </div>
                        @endfor

                        <div class="flex justify-end gap-3">
                            <button type="button"
                                    @click="openBulkPayment = false"
                                    class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Vazgeç
                            </button>

                            <button type="submit"
                                    class="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200/60 transition hover:scale-[1.01]">
                                Toplu Ödeme Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>
@endsection
