@extends('layouts.app')
@section('title', 'Telegram Uyarıları')
@section('subtitle', 'FİLO MERKEZ')

@section('content')
@php
    $cards = ['kasko' => [
        'label' => 'Kasko', 'icon' => '🛡️', 'kind' => 'date', 'description' => 'Kasko poliçesi bitişi',
        'is_enabled' => $setting->is_enabled, 'has_bot_token' => $hasBotToken,
        'chat_id' => $setting->chat_id, 'reminder_days' => $setting->reminder_days ?: \App\Services\TelegramAlertSchedule::DEFAULT_DAYS,
        'last_sent_at' => $kaskoLastSent,
        'message_template' => $setting->message_template ?: \App\Models\TelegramKaskoNotificationSetting::defaultMessageTemplate(),
        'template_fields' => ['company_name', 'days_left', 'kasko_status', 'plate', 'brand_model', 'model_year', 'license_serial_no', 'chassis_no', 'license_owner', 'owner_tax_or_tc_no', 'kasko_end_date'],
    ]] + $channels;
    $active = collect($cards)->filter(fn ($bot) => $bot['is_enabled'] && $bot['has_bot_token'] && filled($bot['chat_id']))->count();
    $card = $cards[$selectedChannel];
    $isKasko = $selectedChannel === 'kasko';
    $configured = $card['has_bot_token'] && filled($card['chat_id']);
    $canEditTemplate = $isKasko || $fleet_templates_ready;
@endphp

<style>
    .tg-hero { background:radial-gradient(ellipse at 84% 10%,#164e78,transparent 52%),linear-gradient(135deg,#142542,#0b152b); }
    .tg-orbit { position:absolute; right:clamp(1rem,8vw,7rem); top:50%; width:9rem; height:9rem; margin-top:-4.5rem; display:grid; place-items:center; perspective:500px; }
    .tg-orbit::before,.tg-orbit::after { content:""; position:absolute; inset:0; border:1px solid #91d8ff70; border-radius:40%; transform:rotateX(56deg) rotateZ(-26deg); }
    .tg-orbit::after { inset:-.8rem; border-color:#6dbcfe40; transform:rotateX(66deg) rotateZ(36deg); }
    .tg-orbit span { z-index:1; width:4.5rem; height:4.5rem; display:grid; place-items:center; border:1px solid #b6e6ff9c; border-radius:1.5rem; background:linear-gradient(145deg,#77d6fb,#287ae3 72%); box-shadow:0 .5rem 0 #174587,0 1.45rem 2rem #07142d99; animation:tg-float 5s ease-in-out infinite; }
    @keyframes tg-float { 0%,100% { transform:translateY(2px) rotateX(15deg) rotateY(-18deg); } 50% { transform:translateY(-10px) rotateX(5deg) rotateY(8deg); } }
    .tg-card { transition:transform .22s ease,box-shadow .22s ease; }
    .tg-icon { box-shadow:0 3px 0 #cee2ef,0 6px 12px #0c4a6e14; }
    .tg-actions { display:flex; flex-wrap:wrap; gap:.65rem; align-items:center; }
    #bot-settings .tg-action { display:inline-flex; min-height:2.75rem; align-items:center; justify-content:center; border:1px solid #b9d3ea; border-radius:.75rem; padding:.65rem 1rem; background:#edf6ff; color:#0b4774; font-size:.875rem; font-weight:800; cursor:pointer; }
    #bot-settings .tg-action-primary { color:#fff; background:#075ea8; border-color:#075ea8; }
    #bot-settings .tg-action-primary:hover { background:#064780; border-color:#064780; }
    #bot-settings .tg-action:focus-visible { outline:3px solid #237ed0; outline-offset:3px; }
    #bot-settings .tg-action:disabled { background:#f1f5f9; border-color:#cbd5e1; color:#475569; cursor:not-allowed; }
    @media(hover:hover) { .tg-card:hover { transform:perspective(650px) translateY(-3px) rotateX(5deg); box-shadow:0 10px 24px #1742671b; } }
    @media(max-width:560px) { .tg-orbit { right:-1.5rem; opacity:.34; scale:.7; } }
    @media(prefers-reduced-motion:reduce) { .tg-card { transition:none; } .tg-card:hover { transform:none; } .tg-orbit span { animation:none; } }
</style>

<div class="mx-auto max-w-6xl space-y-6 p-1">
    @if(session('success'))
        <p role="status" class="rounded-xl bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800">{{ session('success') }}</p>
    @endif
    @if(session('error') || $errors->any())
        <div role="alert" class="rounded-xl bg-rose-50 px-5 py-3 text-sm font-semibold text-rose-800">
            @if(session('error')) <p>{{ session('error') }}</p> @endif
            @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
        </div>
    @endif

    <section class="tg-hero relative overflow-hidden rounded-[30px] px-6 py-9 text-white shadow-xl sm:px-9">
        <div class="tg-orbit" aria-hidden="true"><span class="text-4xl">✈</span></div>
        <div class="relative max-w-md">
            <p class="text-sm font-bold text-sky-200">{{ $company->name }}</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight">Telegram Uyarı Merkezi</h2>
            <p class="mt-4 text-sm font-semibold text-slate-200">{{ $active }} / {{ count($cards) }} bot etkin · Her gün {{ $schedule_time }} Türkiye saati</p>
        </div>
    </section>

    <nav aria-label="Uyarı türleri" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
        @foreach($cards as $key => $item)
            @php($connected = $item['has_bot_token'] && filled($item['chat_id']))
            <a class="tg-card rounded-2xl border px-4 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-700 {{ $selectedChannel === $key ? 'border-sky-500 bg-sky-50 ring-1 ring-sky-500' : 'border-slate-200 bg-white shadow-sm' }}"
                href="{{ route('telegram-kasko.index', ['channel' => $key]) }}#bot-settings"
                @if($selectedChannel === $key) aria-current="page" @endif>
                <span aria-hidden="true" class="tg-icon inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-xl">{{ $item['icon'] }}</span>
                <span class="mt-3 block text-sm font-black text-slate-900">{{ $item['label'] }}</span>
                <span class="mt-1 block text-xs font-bold {{ $connected && $item['is_enabled'] ? 'text-emerald-700' : 'text-slate-500' }}">
                    {{ $connected ? ($item['is_enabled'] ? 'Etkin' : 'Kapalı') : 'Kurulum gerekli' }}
                </span>
            </a>
        @endforeach
    </nav>

    <section id="bot-settings" class="scroll-mt-6 rounded-[26px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-black text-slate-900">{{ $card['icon'] }} {{ $card['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $card['description'] }}</p>
                @if($card['last_sent_at'])
                    <p class="mt-2 text-xs font-semibold text-slate-600">Son otomatik uyarı · {{ \Carbon\Carbon::parse($card['last_sent_at'])->format('d.m.Y H:i') }}</p>
                @endif
            </div>
            @if($isKasko || $fleet_ready)
                <div class="tg-actions">
                    <button type="submit" form="tg-bot-settings-form" class="tg-action tg-action-primary">Ayarları Kaydet</button>
                    <form method="POST" action="{{ $isKasko ? route('telegram-kasko.test') : route('telegram-kasko.bots.test', ['channel' => $selectedChannel]) }}">
                        @csrf
                        <button type="submit" class="tg-action" @disabled(!$configured)>{{ $isKasko ? 'Tek Araç Testi' : 'Örnek Mesaj Gönder' }}</button>
                    </form>
                </div>
            @endif
        </header>

        @if(!$isKasko && !$fleet_ready)
            <p class="rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-900">Botu kurmak için önce <a class="underline" href="{{ route('telegram-kasko.migrate') }}">Telegram veritabanını güncelleyin</a>.</p>
        @else
            <form id="tg-bot-settings-form" method="POST" action="{{ $isKasko ? route('telegram-kasko.update') : route('telegram-kasko.bots.update', ['channel' => $selectedChannel]) }}" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="is_enabled" value="0">
                <label class="flex w-fit cursor-pointer items-center gap-3 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-800">
                    <input type="checkbox" name="is_enabled" value="1" class="rounded text-sky-700" @checked(old('is_enabled', $card['is_enabled']))>
                    Otomatik uyarılar etkin
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-bold text-slate-800">Bot anahtarı
                        <input type="password" name="bot_token" autocomplete="new-password" maxlength="255" class="mt-2 w-full rounded-xl border-slate-300" placeholder="{{ $card['has_bot_token'] ? 'Kayıtlı · Yeni anahtarla değiştir' : 'BotFather anahtarı' }}">
                    </label>
                    <label class="block text-sm font-bold text-slate-800">Sohbet / grup kimliği
                        <input type="text" name="chat_id" maxlength="100" class="mt-2 w-full rounded-xl border-slate-300" value="{{ old('chat_id', $card['chat_id']) }}" placeholder="Örn. -1001234567890">
                    </label>
                </div>
                @if($card['kind'] === 'date')
                    <label class="block text-sm font-bold text-slate-800">Kalan gün eşikleri
                        <input type="text" required maxlength="500" name="reminder_days_text" class="mt-2 w-full rounded-xl border-slate-300" value="{{ old('reminder_days_text', implode(', ', $card['reminder_days'])) }}">
                        <span class="mt-2 block text-xs font-medium text-slate-600">Günleri virgülle ayırın. Kaçan eşikte sistem geçerli son eşiği bir kez bildirir.</span>
                    </label>
                @elseif($card['kind'] === 'maintenance')
                    <label class="block text-sm font-bold text-slate-800">Bakım uyarı mesafesi (km)
                        <input type="number" min="0" max="100000" required name="maintenance_km" class="mt-2 block w-36 rounded-xl border-slate-300" value="{{ old('maintenance_km', $card['maintenance_km']) }}">
                    </label>
                @else
                    <p class="text-sm text-slate-600">Bot, bugün ve dün girilen yakıt alımlarındaki km/litre değerini araç için kayıtlı alt sınırla karşılaştırır.</p>
                @endif
                @if($isKasko)
                    <input type="hidden" name="upcoming_summary_enabled" value="0">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-800">
                        <input type="checkbox" name="upcoming_summary_enabled" value="1" class="mt-1 rounded text-sky-700" @checked(old('upcoming_summary_enabled', $setting->upcoming_summary_enabled ?? true))>
                        <span><b>Günlük yaklaşan kasko özeti</b><span class="mt-1 block text-xs text-slate-600">En büyük eşik özet aralığını belirler. Yenileme yüklenince yeni bitiş tarihi kullanılır.</span></span>
                    </label>
                @endif
                <details class="rounded-xl border border-slate-200 p-4" @if(!$isKasko || $errors->has('message_template')) open @endif>
                    <summary class="cursor-pointer text-sm font-bold text-slate-800">Telegram mesaj şablonu</summary>
                    <textarea aria-label="Telegram mesaj şablonu" name="message_template" maxlength="{{ $isKasko ? 4000 : \App\Services\TelegramFleetAlertMessage::MAX_TEMPLATE_LENGTH }}" rows="12" class="mt-3 w-full rounded-xl border-slate-300 font-mono text-sm" @disabled(!$canEditTemplate)>{{ old('message_template', $card['message_template']) }}</textarea>
                    @if(!$canEditTemplate)
                        <p class="mt-2 text-sm text-slate-700">Şablonu düzenlemek için önce <a class="font-bold underline" href="{{ route('telegram-kasko.migrate') }}">Telegram veritabanını güncelleyin</a>.</p>
                    @else
                        <p class="mt-2 text-xs text-slate-600">Alanlar araç bilgileriyle otomatik dolar. {{ $isKasko ? '' : 'Hazır şablona dönmek için metni silip kaydedin.' }} Test düğmesi kayıtlı şablonu kullanır.</p>
                        <p class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                            @foreach($card['template_fields'] as $field)
                                <code>&#123;&#123;{{ $field }}&#125;&#125;</code>
                            @endforeach
                        </p>
                    @endif
                </details>
            </form>

            @if($isKasko && $configured)
                <form class="mt-5 border-t border-slate-200 pt-5" method="POST" action="{{ route('telegram-kasko.connect') }}">
                    @csrf
                    <button type="submit" class="tg-action">Grup yanıtlarını bağla</button>
                </form>
                <p class="mt-3 text-xs text-slate-600">Grup komutları · kaskolu araçlarım · süresi geçenler · kaskosu olmayan araçlar</p>
            @endif
        @endif
    </section>
</div>
@endsection
