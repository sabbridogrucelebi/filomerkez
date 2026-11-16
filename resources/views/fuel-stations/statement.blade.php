@extends('layouts.app')

@section('title', 'İstasyon Ekstresi')
@section('subtitle', $station->name . ' cari hareketleri')

@section('content')
<div class="space-y-6">
    <div class="rounded-[32px] border border-slate-200/70 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.08)] overflow-hidden">
        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-emerald-50/30 px-6 py-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-[26px] font-bold tracking-tight text-slate-900">{{ $station->name }} Ekstresi</h2>
                    <p class="mt-1 text-sm text-slate-500">Yakıt fişleri, ödemeler ve cari bakiye görünümü</p>
                </div>

                <a href="{{ route('fuel-stations.index') }}"
                   class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    İstasyonlara Dön
                </a>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <form method="GET" action="{{ route('fuel-stations.statement', $station) }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Başlangıç</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.10em] text-slate-400">Bitiş</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
                </div>
                <div class="xl:col-span-2 flex items-end justify-end gap-3">
                    <a href="{{ route('fuel-stations.statement', $station) }}"
                       class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Temizle
                    </a>
                    <button type="submit"
                            class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-3 text-sm font-semibold text-white">
                        Filtrele
                    </button>
                </div>
            </form>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs uppercase tracking-[0.10em] text-slate-400 font-semibold">Toplam Litre</div>
                    <div class="mt-2 text-xl font-bold text-slate-800">{{ number_format($summary['total_liters'], 2, ',', '.') }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-xs uppercase tracking-[0.10em] text-slate-400 font-semibold">Yakıt Tutarı</div>
                        @if(($summary['discount_total'] ?? 0) > 0)
                            <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200" title="İskonto Uygulandı">
                                -{{ number_format($summary['discount_total'], 2, ',', '.') }} ₺ İskonto
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 text-xl font-bold text-slate-800">{{ number_format($summary['total_fuel_cost'], 2, ',', '.') }} ₺</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
                    <div class="text-xs uppercase tracking-[0.10em] text-emerald-600 font-semibold">Toplam Ödeme</div>
                    <div class="mt-2 text-xl font-bold text-emerald-700">{{ number_format($summary['total_paid'], 2, ',', '.') }} ₺</div>
                </div>
                <div class="rounded-2xl {{ $summary['current_debt'] > 0 ? 'bg-rose-50 border border-rose-200' : 'bg-emerald-50 border border-emerald-200' }} p-4">
                    <div class="text-xs uppercase tracking-[0.10em] {{ $summary['current_debt'] > 0 ? 'text-rose-600' : 'text-emerald-600' }} font-semibold">Cari Bakiye</div>
                    <div class="mt-2 text-xl font-bold {{ $summary['current_debt'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                        {{ number_format($summary['current_debt'], 2, ',', '.') }} ₺
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="rounded-[24px] border border-slate-200/70 overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <h3 class="font-bold text-slate-800">Yakıt Fişleri</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px]">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Tarih</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Araç</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Litre</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Tutar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($fuels as $fuel)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ optional($fuel->date)->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $fuel->vehicle?->plate ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ number_format($fuel->liters, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-slate-800">{{ number_format($fuel->effective_cost ?? $fuel->total_cost, 2, ',', '.') }} ₺</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Yakıt kaydı yok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-[24px] border border-slate-200/70 overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <h3 class="font-bold text-slate-800">Ödemeler</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px]">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Tarih</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Yöntem</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Not</th>
                                    <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Tutar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ optional($payment->payment_date)->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $payment->payment_method }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-500">{{ $payment->notes ?: '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-semibold text-emerald-700">{{ number_format($payment->amount, 2, ',', '.') }} ₺</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Ödeme kaydı yok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
