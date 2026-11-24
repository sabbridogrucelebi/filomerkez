@extends('layouts.app')

@section('title', 'Telegram Kasko Uyarıları')
@section('subtitle', 'BOT BİLDİRİM OTOMASYONU')

@section('content')
@php
    $reminderDaysText = old('reminder_days_text', implode(', ', $setting->reminder_days ?: [1, 3, 7, 10, 15, 20]));
@endphp

<div class="mx-auto w-full max-w-6xl space-y-6 p-1">
    @if(session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-800">
            <span class="text-xl">✅</span><p class="text-sm font-bold">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-800">
            <span class="text-xl">⚠️</span><p class="text-sm font-bold">{{ session('error') }}</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-[32px] bg-gradient-to-br from-sky-500 via-blue-600 to-indigo-700 p-[1px] shadow-xl shadow-blue-200/60">
        <div class="relative overflow-hidden rounded-[31px] bg-slate-950 px-7 py-8 text-white sm:px-10">
            <div class="absolute -right-12 -top-16 h-56 w-56 rounded-full bg-sky-400/20 blur-3xl"></div>
            <div class="absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-indigo-400/20 blur-3xl"></div>
            <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-5">
                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl border border-white/20 bg-white/10 text-4xl shadow-2xl">✈️</div>
                    <div>
                        <div class="mb-2 flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $setting->is_enabled ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span><span class="text-[10px] font-black uppercase tracking-[0.24em] text-sky-200">{{ $setting->is_enabled ? 'Aktif otomasyon' : 'Otomasyon beklemede' }}</span></div>
                        <h2 class="text-2xl font-black tracking-tight">Telegram Kasko Asistanı</h2>
                        <p class="mt-1 text-sm font-medium text-slate-300">Kasko bitiş tarihlerinde seçtiğiniz günlerde şık araç detaylarıyla otomatik bildirim gönderir.</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-widest text-sky-200">Firma</p>
                    <p class="mt-1 text-sm font-extrabold">{{ $company->name }}</p>
                    <p class="mt-1 text-xs text-slate-300">Her gün 09:00'da kontrol edilir.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
        <form method="POST" action="{{ route('telegram-kasko.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-7 flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-xl">🤖</div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900">Bot bağlantısı</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">BotFather'dan aldığınız anahtarı ve mesajın gönderileceği sohbet/grup kimliğini girin.</p>
                    </div>
                </div>

                <label class="mb-5 flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <span><span class="block text-sm font-extrabold text-slate-800">Otomatik uyarıları etkinleştir</span><span class="mt-1 block text-xs font-medium text-slate-500">Bireysel uyarılar ve günlük yaklaşan kasko özeti birlikte çalışır.</span></span>
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" name="is_enabled" value="1" class="peer sr-only" {{ old('is_enabled', $setting->is_enabled) ? 'checked' : '' }}>
                        <span class="h-7 w-12 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </span>
                </label>
                @error('is_enabled')<p class="mb-4 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="bot_token" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Bot anahtarı</label>
                        <input id="bot_token" name="bot_token" type="password" autocomplete="new-password" placeholder="{{ $hasBotToken ? 'Kayıtlı — değiştirmek için yeni anahtarı yazın' : '123456:ABC-DEF...' }}" class="w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500">
                        @error('bot_token')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="chat_id" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Sohbet / grup kimliği</label>
                        <input id="chat_id" name="chat_id" value="{{ old('chat_id', $setting->chat_id) }}" placeholder="Örn: -1001234567890" class="w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:ring-sky-500">
                        @error('chat_id')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6 flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-xl">⏰</div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900">Uyarı günleri</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">Kaskonun bitmesine kaç gün kala mesaj gideceğini yazın. Virgülle birden fazla gün ekleyebilirsiniz.</p>
                    </div>
                </div>
                <label for="reminder_days_text" class="mb-2 block text-xs font-black uppercase tracking-wider text-slate-600">Kalan günler</label>
                <input id="reminder_days_text" name="reminder_days_text" value="{{ $reminderDaysText }}" inputmode="numeric" placeholder="Örn: 20, 15, 10, 7, 3, 1" class="w-full rounded-xl border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 focus:border-sky-500 focus:ring-sky-500">
                <p class="mt-2 text-xs font-medium text-slate-400">Önerilen ayar: <b>20, 15, 10, 7, 3, 1</b>. Bu günlerde araç bazlı bildirim gider. Süre bittikten sonra sistem belge yenilenmediyse otomatik olarak <b>1. gün</b> ve son kez <b>5. gün</b> uyarı verir.</p>
                @error('reminder_days_text')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6 flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-xl">📋</div>
                    <div><h3 class="text-lg font-black text-slate-900">Günlük yaklaşan kasko özeti</h3><p class="mt-1 text-sm font-medium text-slate-500">Her gün 09:00'da, seçtiğiniz gün aralığında bitecek tüm aktif poliçeleri tek mesajda listeler.</p></div>
                </div>
                <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <span><span class="block text-sm font-extrabold text-slate-800">Günlük özeti etkinleştir</span><span class="mt-1 block text-xs font-medium text-slate-500">Aynı gün içinde bir kez gönderilir.</span></span>
                    <span class="relative inline-flex items-center"><input type="checkbox" name="upcoming_summary_enabled" value="1" class="peer sr-only" {{ old('upcoming_summary_enabled', $setting->upcoming_summary_enabled ?? true) ? 'checked' : '' }}><span class="h-7 w-12 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500 after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span></span>
                </label>
                <label for="upcoming_summary_days" class="mb-2 mt-5 block text-xs font-black uppercase tracking-wider text-slate-600">Özet penceresi</label>
                <div class="flex items-center gap-3"><input id="upcoming_summary_days" name="upcoming_summary_days" type="number" min="1" max="365" value="{{ old('upcoming_summary_days', $setting->upcoming_summary_days ?? 20) }}" class="w-28 rounded-xl border-slate-200 px-4 py-3 text-sm font-bold text-slate-800 focus:border-sky-500 focus:ring-sky-500"><span class="text-sm font-medium text-slate-500">gün içinde bitecek kaskolar</span></div>
                @error('upcoming_summary_days')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6 flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-xl">✨</div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900">Premium uyarı metni</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">Metni istediğiniz gibi kişiselleştirin. Aşağıdaki alanlar mesaj gönderilirken otomatik doldurulur.</p>
                    </div>
                </div>
                <textarea id="message_template" name="message_template" rows="16" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-4 font-mono text-sm leading-6 text-slate-800 focus:border-sky-500 focus:ring-sky-500">{{ old('message_template', $setting->message_template ?: \App\Models\TelegramKaskoNotificationSetting::defaultMessageTemplate()) }}</textarea>
                @error('message_template')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach(['company_name','days_left','kasko_status','plate','brand_model','model_year','license_serial_no','chassis_no','kasko_end_date'] as $field)
                        <code class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">&#123;&#123;{{ $field }}&#125;&#125;</code>
                    @endforeach
                </div>
                <p class="mt-3 text-xs font-medium text-slate-400">Telegram biçimi için <code>&lt;b&gt;kalın&lt;/b&gt;</code> ve <code>&lt;i&gt;italik&lt;/i&gt;</code> kullanabilirsiniz.</p>
            </section>

            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-600 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-sky-200 transition hover:-translate-y-0.5 hover:shadow-xl">💾 Ayarları Kaydet</button>
            </div>
        </form>

        <aside class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3"><span class="text-2xl">🧪</span><h3 class="text-base font-black text-slate-900">Mesajı dene</h3></div>
                <p class="mt-3 text-sm font-medium leading-6 text-slate-500">Test, yüklü kasko belgesinde bitiş tarihi en yakın aracın mesajını gönderir. Geçerli belge yoksa gruba bağlantı testi gönderilir.</p>
                <form method="POST" action="{{ route('telegram-kasko.test') }}" class="mt-5">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-black text-sky-700 transition hover:bg-sky-100">✈️ Test Mesajı Gönder</button>
                </form>
                <form method="POST" action="{{ route('telegram-kasko.connect') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-black text-white">Grup yanıtlarını bağla</button>
                </form>
                <p class="mt-4 text-sm font-black text-slate-900">Telegram grup komutları</p>
                <ul class="mt-2 space-y-2 text-sm text-slate-600">
                    <li><b>kaskosu olmayan araçlar</b> — Belgesi eksik, tarihi tanımsız veya poliçesi henüz başlamamış araçlar.</li>
                    <li><b>süresi geçenler</b> — Kaskosu bitmiş ve yenilenmemiş araçlar.</li>
                    <li><b>kaskolu araçlarım</b> — Geçerli poliçelerin sayısı, başlangıç ve bitiş tarihleri, kalan günleri.</li>
                </ul>
            </div>

            <div class="rounded-[28px] border border-indigo-100 bg-indigo-50/70 p-6">
                <h3 class="flex items-center gap-2 text-base font-black text-indigo-950"><span>🔐</span> Güvenli bağlantı</h3>
                <ul class="mt-4 space-y-3 text-sm font-medium leading-6 text-indigo-900/70">
                    <li>• Bot anahtarı ve sohbet kimliği veritabanında şifreli saklanır.</li>
                    <li>• Aynı araç için aynı bitiş tarihi ve gün eşiğinde tekrar mesaj gönderilmez.</li>
                    <li>• Süresi biten kasko için 1. gün ve yeni belge yoksa 5. gün son kez uyarı verilir.</li>
                </ul>
            </div>

            <div class="rounded-[28px] bg-slate-900 p-6 text-slate-200 shadow-xl shadow-slate-200">
                <h3 class="flex items-center gap-2 text-base font-black text-white"><span>📌</span> Kurulum notu</h3>
                <ol class="mt-4 space-y-3 text-sm font-medium leading-6 text-slate-300">
                    <li><b class="text-white">1.</b> Telegram'da BotFather ile bir bot oluşturun.</li>
                    <li><b class="text-white">2.</b> Botu mesajın gideceği grup ya da sohbete ekleyin.</li>
                    <li><b class="text-white">3.</b> Bot anahtarını ve sohbet kimliğini buraya kaydedip test edin.</li>
                </ol>
            </div>
        </aside>
    </div>
</div>
@endsection
