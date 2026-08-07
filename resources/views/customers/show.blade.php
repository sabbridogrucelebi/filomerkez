@extends('layouts.app')

@section('title', 'Müşteri Detayı')
@section('subtitle', 'Müşteri yönetim merkezi')

@section('content')

@php
    $activeTab = request('tab', 'company');

    $customerTypeIcon = match($customer->customer_type) {
        'Fabrika' => '🏭',
        'Okul' => '🏫',
        'Resmi Daire' => '🏛️',
        'Diğer Servisler' => '🧾',
        default => '🏢',
    };

    $customerTypeGradient = match($customer->customer_type) {
        'Fabrika' => 'from-orange-500 via-amber-500 to-yellow-500',
        'Okul' => 'from-blue-500 via-cyan-500 to-sky-500',
        'Resmi Daire' => 'from-violet-500 via-fuchsia-500 to-purple-500',
        'Diğer Servisler' => 'from-emerald-500 via-teal-500 to-cyan-500',
        default => 'from-slate-700 via-slate-800 to-slate-900',
    };

    $tabClass = function ($key) use ($activeTab) {
        return $activeTab === $key
            ? 'inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/10'
            : 'inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition';
    };

    $isContractExpired = $customer->contract_end_date && $customer->contract_end_date->isPast();
    $isContractEndingSoon = $customer->contract_end_date
        && $customer->contract_end_date->isFuture()
        && $customer->contract_end_date->lte(now()->copy()->addDays(30));

    $daysRemaining = $customer->contract_end_date
        ? now()->startOfDay()->diffInDays($customer->contract_end_date->startOfDay(), false)
        : null;

    $statusText = $customer->is_active ? 'Aktif Müşteri' : 'Pasif Müşteri';
    $statusBadgeClass = $customer->is_active
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-rose-100 text-rose-700';
@endphp

<div class="space-y-6">

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

    <div class="relative overflow-hidden rounded-[34px] border border-slate-200/70 bg-white shadow-xl shadow-slate-200/60">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-white to-blue-50/60"></div>
        <div class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-blue-100/40 blur-2xl"></div>
        <div class="absolute -bottom-20 -left-10 h-48 w-48 rounded-full bg-indigo-100/40 blur-2xl"></div>

        <div class="relative px-6 py-6 md:px-8 md:py-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[22px] bg-gradient-to-br {{ $customerTypeGradient }} text-3xl text-white shadow-lg">
                        {{ $customerTypeIcon }}
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $customer->customer_type ?: 'Müşteri Kaydı' }}
                            </span>

                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">
                            {{ $customer->company_name }}
                        </h2>

                        <p class="mt-2 text-sm font-medium text-slate-500 md:text-base">
                            {{ $customer->company_title ?: 'Firma ünvanı belirtilmemiş.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-5 text-sm text-slate-500">
                            <div>
                                <span class="font-semibold text-slate-700">Yetkili:</span>
                                {{ $customer->authorized_person ?: '-' }}
                            </div>
                            <div>
                                <span class="font-semibold text-slate-700">Telefon:</span>
                                {{ $customer->authorized_phone ?: '-' }}
                            </div>
                            <div>
                                <span class="font-semibold text-slate-700">E-Posta:</span>
                                {{ $customer->email ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('customers.index') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <span>←</span>
                        <span>Listeye Dön</span>
                    </a>

                    <a href="{{ route('customers.edit', $customer) }}"
                       class="inline-flex items-center gap-2 rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-amber-500/20 transition hover:bg-amber-600">
                        <span>✏️</span>
                        <span>Düzenle</span>
                    </a>

                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Bu müşteriyi silmek istediğine emin misin?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100">
                            <span>🗑️</span>
                            <span>Sil</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-orange-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Müşteri Türü</div>
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-100 text-xl">
                        {{ $customerTypeIcon }}
                    </div>
                    <div>
                        <div class="text-xl font-extrabold text-slate-900">{{ $customer->customer_type ?: '-' }}</div>
                        <div class="mt-1 text-xs text-slate-500">Tanımlı müşteri sınıfı</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-emerald-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">KDV Oranı</div>
                <div class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">
                    %{{ $customer->vat_rate ? rtrim(rtrim((string) $customer->vat_rate, '0'), '.') : '0' }}
                </div>
                <div class="mt-2 text-xs text-slate-500">Faturalandırmada varsayılan oran</div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-violet-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Tevkifat</div>
                <div class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">
                    {{ $customer->withholding_rate ?: 'Yok' }}
@extends('layouts.app')

@section('title', 'Müşteri Detayı')
@section('subtitle', 'Müşteri yönetim merkezi')

@section('content')

@php
    $activeTab = request('tab', 'company');

    $customerTypeIcon = match($customer->customer_type) {
        'Fabrika' => '🏭',
        'Okul' => '🏫',
        'Resmi Daire' => '🏛️',
        'Diğer Servisler' => '🧾',
        default => '🏢',
    };

    $customerTypeGradient = match($customer->customer_type) {
        'Fabrika' => 'from-orange-500 via-amber-500 to-yellow-500',
        'Okul' => 'from-blue-500 via-cyan-500 to-sky-500',
        'Resmi Daire' => 'from-violet-500 via-fuchsia-500 to-purple-500',
        'Diğer Servisler' => 'from-emerald-500 via-teal-500 to-cyan-500',
        default => 'from-slate-700 via-slate-800 to-slate-900',
    };

    $tabClass = function ($key) use ($activeTab) {
        return $activeTab === $key
            ? 'inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/10'
            : 'inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition';
    };

    $isContractExpired = $customer->contract_end_date && $customer->contract_end_date->isPast();
    $isContractEndingSoon = $customer->contract_end_date
        && $customer->contract_end_date->isFuture()
        && $customer->contract_end_date->lte(now()->copy()->addDays(30));

    $daysRemaining = $customer->contract_end_date
        ? now()->startOfDay()->diffInDays($customer->contract_end_date->startOfDay(), false)
        : null;

    $statusText = $customer->is_active ? 'Aktif Müşteri' : 'Pasif Müşteri';
    $statusBadgeClass = $customer->is_active
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-rose-100 text-rose-700';
@endphp

<div class="space-y-6">

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

    <div class="relative overflow-hidden rounded-[34px] border border-slate-200/70 bg-white shadow-xl shadow-slate-200/60">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-white to-blue-50/60"></div>
        <div class="absolute -top-16 -right-16 h-48 w-48 rounded-full bg-blue-100/40 blur-2xl"></div>
        <div class="absolute -bottom-20 -left-10 h-48 w-48 rounded-full bg-indigo-100/40 blur-2xl"></div>

        <div class="relative px-6 py-6 md:px-8 md:py-8">
            <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[22px] bg-gradient-to-br {{ $customerTypeGradient }} text-3xl text-white shadow-lg">
                        {{ $customerTypeIcon }}
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ $customer->customer_type ?: 'Müşteri Kaydı' }}
                            </span>

                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadgeClass }}">
                                {{ $statusText }}
                            </span>
                        </div>

                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">
                            {{ $customer->company_name }}
                        </h2>

                        <p class="mt-2 text-sm font-medium text-slate-500 md:text-base">
                            {{ $customer->company_title ?: 'Firma ünvanı belirtilmemiş.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-5 text-sm text-slate-500">
                            <div>
                                <span class="font-semibold text-slate-700">Yetkili:</span>
                                {{ $customer->authorized_person ?: '-' }}
                            </div>
                            <div>
                                <span class="font-semibold text-slate-700">Telefon:</span>
                                {{ $customer->authorized_phone ?: '-' }}
                            </div>
                            <div>
                                <span class="font-semibold text-slate-700">E-Posta:</span>
                                {{ $customer->email ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('customers.index') }}"
                       class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        <span>←</span>
                        <span>Listeye Dön</span>
                    </a>

                    <a href="{{ route('customers.edit', $customer) }}"
                       class="inline-flex items-center gap-2 rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-amber-500/20 transition hover:bg-amber-600">
                        <span>✏️</span>
                        <span>Düzenle</span>
                    </a>

                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Bu müşteriyi silmek istediğine emin misin?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100">
                            <span>🗑️</span>
                            <span>Sil</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-orange-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Müşteri Türü</div>
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-100 text-xl">
                        {{ $customerTypeIcon }}
                    </div>
                    <div>
                        <div class="text-xl font-extrabold text-slate-900">{{ $customer->customer_type ?: '-' }}</div>
                        <div class="mt-1 text-xs text-slate-500">Tanımlı müşteri sınıfı</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-emerald-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">KDV Oranı</div>
                <div class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">
                    %{{ $customer->vat_rate ? rtrim(rtrim((string) $customer->vat_rate, '0'), '.') : '0' }}
                </div>
                <div class="mt-2 text-xs text-slate-500">Faturalandırmada varsayılan oran</div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-violet-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Tevkifat</div>
                <div class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900">
                    {{ $customer->withholding_rate ?: 'Yok' }}
                </div>
                <div class="mt-2 text-xs text-slate-500">Muhasebe için kayıtlı oran</div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[28px] border border-slate-200/60 bg-white p-5 shadow-lg shadow-slate-200/40">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-full bg-blue-100/60 blur-2xl"></div>
            <div class="relative">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Sözleşme Durumu</div>
                @if($activeContract)
                    <div class="mt-4 text-xl font-extrabold text-emerald-600">Geçerli</div>
                    <div class="mt-2 text-xs text-slate-500">{{ $activeContract->year }} yılı sözleşmesi aktif</div>
                @else
                    <div class="mt-4 text-xl font-extrabold text-rose-600">Süresi Dolmuş</div>
                    <div class="mt-2 text-xs text-slate-500">Geçerli sözleşme bulunamadı</div>
                @endif
            </div>
        </div>

    </div>

    <div class="rounded-[30px] border border-slate-200/70 bg-white p-4 shadow-lg shadow-slate-200/40">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('customers.show', [$customer, 'tab' => 'company']) }}" class="{{ $tabClass('company') }}">
                <span>🏢</span>
                <span>Firma Bilgileri</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'services']) }}" class="{{ $tabClass('services') }}">
                <span>🛣️</span>
                <span>Servisler</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'documents']) }}" class="{{ $tabClass('documents') }}">
                <span>📁</span>
                <span>Belge ve Dökümanlar</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'invoices']) }}" class="{{ $tabClass('invoices') }}">
                <span>🧾</span>
                <span>Faturalar</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'contracts']) }}" class="{{ $tabClass('contracts') }}">
                <span>📄</span>
                <span>Sözleşmeler</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'users']) }}" class="{{ $tabClass('users') }}">
                <span>👥</span>
                <span>Kullanıcılar</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'joint-ventures']) }}" class="{{ $tabClass('joint-ventures') }}">
                <span>🤝</span>
                <span>Ortak Girişim</span>
            </a>

            <a href="{{ route('customers.show', [$customer, 'tab' => 'statement']) }}" class="{{ $tabClass('statement') }}">
                <span>📊</span>
                <span>Cari Ekstre (Detaylı)</span>
            </a>
        </div>
    </div>

    @if($activeTab === 'company')
        @include('customers.partials.company-tab', [
            'customer' => $customer,
            'statusText' => $statusText,
            'isContractExpired' => $isContractExpired,
            'isContractEndingSoon' => $isContractEndingSoon,
            'daysRemaining' => $daysRemaining,
            'customerTypeIcon' => $customerTypeIcon,
        ])
    @endif

    @if($activeTab === 'services')
        @include('customers.partials.services-tab')
    @endif

    @if($activeTab === 'documents')
        @include('customers.partials.documents-tab')
    @endif

    @if($activeTab === 'invoices')
        @include('customers.partials.invoices-tab')
    @endif

    @if($activeTab === 'contracts')
        @include('customers.partials.contracts-tab', [
            'customer' => $customer,
            'contracts' => $contracts,
            'activeContract' => $activeContract,
        ])
    @endif

    @if($activeTab === 'users')
        @include('customers.partials.users-tab')
    @endif

    @if($activeTab === 'joint-ventures')
        @include('customers.partials.joint-ventures-tab')
    @endif

    @if($activeTab === 'statement')
        @include('customers.partials.statement-tab')
    @endif

</div>

@endsection
