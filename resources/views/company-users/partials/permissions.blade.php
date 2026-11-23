{{-- Premium Modül Bazlı Yetki Kartları — Master Toggle + Alt Yetkiler --}}
@php
    $modules = [
        ['title'=>'Ana Sayfa','icon'=>'🏠','gradient'=>'from-slate-600 to-slate-800','shadow'=>'shadow-slate-500/25','bg'=>'bg-slate-50','border'=>'border-slate-300','text'=>'text-slate-600',
         'keys'=>['dashboard.view'],'labels'=>['Gösterge Paneli Erişimi']],
        ['title'=>'Araçlar','icon'=>'🚗','gradient'=>'from-blue-500 to-indigo-600','shadow'=>'shadow-blue-500/25','bg'=>'bg-blue-50','border'=>'border-blue-200','text'=>'text-blue-600',
         'keys'=>['vehicles.view','vehicles.create','vehicles.edit','vehicles.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Araç Takip','icon'=>'📡','gradient'=>'from-cyan-500 to-blue-600','shadow'=>'shadow-cyan-500/25','bg'=>'bg-cyan-50','border'=>'border-cyan-200','text'=>'text-cyan-600',
         'keys'=>['vehicle_tracking.view'],'labels'=>['Canlı Takip Erişimi']],
        ['title'=>'Personeller','icon'=>'👤','gradient'=>'from-violet-500 to-purple-600','shadow'=>'shadow-violet-500/25','bg'=>'bg-violet-50','border'=>'border-violet-200','text'=>'text-violet-600',
         'keys'=>['drivers.view','drivers.create','drivers.edit','drivers.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Bakım / Tamir','icon'=>'🔧','gradient'=>'from-emerald-500 to-teal-600','shadow'=>'shadow-emerald-500/25','bg'=>'bg-emerald-50','border'=>'border-emerald-200','text'=>'text-emerald-600',
         'keys'=>['maintenances.view','maintenances.create','maintenances.edit','maintenances.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Yakıt','icon'=>'⛽','gradient'=>'from-amber-500 to-orange-600','shadow'=>'shadow-amber-500/25','bg'=>'bg-amber-50','border'=>'border-amber-200','text'=>'text-amber-600',
         'keys'=>['fuels.view','fuels.create','fuels.edit','fuels.delete','fuel_stations.view','fuel_stations.create','fuel_stations.edit','fuel_stations.delete'],
         'labels'=>['Yakıt Görüntüleme','Yakıt Ekleme','Yakıt Düzenleme','Yakıt Silme','İstasyon Görüntüleme','İstasyon Ekleme','İstasyon Düzenleme','İstasyon Silme']],
        ['title'=>'Trafik Cezaları','icon'=>'🚨','gradient'=>'from-rose-500 to-pink-600','shadow'=>'shadow-rose-500/25','bg'=>'bg-rose-50','border'=>'border-rose-200','text'=>'text-rose-600',
         'keys'=>['penalties.view','penalties.create','penalties.edit','penalties.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Puantaj / Sefer','icon'=>'📅','gradient'=>'from-sky-500 to-cyan-600','shadow'=>'shadow-sky-500/25','bg'=>'bg-sky-50','border'=>'border-sky-200','text'=>'text-sky-600',
         'keys'=>['trips.view','trips.create','trips.edit','trips.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Maaşlar','icon'=>'💰','gradient'=>'from-lime-500 to-green-600','shadow'=>'shadow-lime-500/25','bg'=>'bg-lime-50','border'=>'border-lime-200','text'=>'text-lime-600',
         'keys'=>['payrolls.view','payrolls.create','payrolls.edit','payrolls.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Müşteriler','icon'=>'🏢','gradient'=>'from-teal-500 to-emerald-600','shadow'=>'shadow-teal-500/25','bg'=>'bg-teal-50','border'=>'border-teal-200','text'=>'text-teal-600',
         'keys'=>['customers.view','customers.create','customers.edit','customers.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Belgeler','icon'=>'📄','gradient'=>'from-slate-500 to-slate-700','shadow'=>'shadow-slate-500/25','bg'=>'bg-slate-50','border'=>'border-slate-200','text'=>'text-slate-600',
         'keys'=>['documents.view','documents.create','documents.edit','documents.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Raporlar & Finans','icon'=>'📊','gradient'=>'from-indigo-500 to-blue-600','shadow'=>'shadow-indigo-500/25','bg'=>'bg-indigo-50','border'=>'border-indigo-200','text'=>'text-indigo-600',
         'keys'=>['reports.view','reports.export','financials.view'],'labels'=>['Rapor Görüntüleme','Dışa Aktarma','Finansal Özet']],
        ['title'=>'PilotChat','icon'=>'💬','gradient'=>'from-fuchsia-500 to-pink-600','shadow'=>'shadow-fuchsia-500/25','bg'=>'bg-fuchsia-50','border'=>'border-fuchsia-200','text'=>'text-fuchsia-600',
         'keys'=>['chat.view','chat.create'],'labels'=>['Mesajlaşma Görüntüleme','Mesaj Gönderme']],
        ['title'=>'Loglar','icon'=>'📜','gradient'=>'from-stone-500 to-stone-700','shadow'=>'shadow-stone-500/25','bg'=>'bg-stone-50','border'=>'border-stone-200','text'=>'text-stone-600',
         'keys'=>['logs.view'],'labels'=>['Sistem Loglarını Görüntüleme']],
        ['title'=>'Kullanıcılar','icon'=>'👥','gradient'=>'from-purple-600 to-indigo-700','shadow'=>'shadow-purple-500/25','bg'=>'bg-purple-50','border'=>'border-purple-200','text'=>'text-purple-600',
         'keys'=>['company_users.view','company_users.create','company_users.edit','company_users.delete'],'labels'=>['Görüntüleme','Ekleme','Düzenleme','Silme']],
        ['title'=>'Abonelik & Ödeme','icon'=>'💳','gradient'=>'from-yellow-500 to-amber-600','shadow'=>'shadow-yellow-500/25','bg'=>'bg-yellow-50','border'=>'border-yellow-200','text'=>'text-yellow-700',
         'keys'=>['billing.view'],'labels'=>['Abonelik & Ödeme Erişimi']],
        ['title'=>'Destek','icon'=>'🆘','gradient'=>'from-red-500 to-rose-600','shadow'=>'shadow-red-500/25','bg'=>'bg-red-50','border'=>'border-red-200','text'=>'text-red-600',
         'keys'=>['support.view','support.create'],'labels'=>['Destek Görüntüleme','Talep Oluşturma']],
        ['title'=>'PilotCell','icon'=>'📱','gradient'=>'from-green-500 to-emerald-600','shadow'=>'shadow-green-500/25','bg'=>'bg-green-50','border'=>'border-green-200','text'=>'text-green-600',
         'keys'=>['pilotcell.view','pilotcell.manage'],'labels'=>['Görüntüleme','Yönetim']],
        ['title'=>'Ayarlar','icon'=>'⚙️','gradient'=>'from-zinc-500 to-zinc-700','shadow'=>'shadow-zinc-500/25','bg'=>'bg-zinc-50','border'=>'border-zinc-200','text'=>'text-zinc-600',
         'keys'=>['settings.view','settings.edit'],'labels'=>['Görüntüleme','Düzenleme']],
        ['title'=>'Telegram','icon'=>'✈️','gradient'=>'from-sky-500 to-blue-700','shadow'=>'shadow-sky-500/25','bg'=>'bg-sky-50','border'=>'border-sky-200','text'=>'text-sky-600',
         'keys'=>['telegram.manage'],'labels'=>['Kasko Uyarılarını Yönetme']],
        ['title'=>'Yedeklemeler','icon'=>'💾','gradient'=>'from-gray-600 to-gray-800','shadow'=>'shadow-gray-500/25','bg'=>'bg-gray-50','border'=>'border-gray-300','text'=>'text-gray-600',
         'keys'=>['backups.view','backups.create'],'labels'=>['Görüntüleme','Oluşturma']],
    ];
    $permKeyToId = $permissions->pluck('id', 'key')->toArray();
    $selectedPerms = $selectedPermissions ?? old('permissions', []);
    $categoryBreaks = [0=>'Genel',1=>'Filo Yönetimi',4=>'Operasyon & Finans',12=>'İletişim & Sistem'];
@endphp

<div class="rounded-[32px] border border-slate-200 bg-white p-8 shadow-sm">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04a11.357 11.357 0 00-1.018 4.772c0 4.113 2.193 7.713 5.5 9.69a11.354 11.354 0 0011.001 0c3.307-1.977 5.5-5.577 5.5-9.69a11.357 11.357 0 00-1.018-4.772z"></path></svg>
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Menü Erişimi</h2>
                <p class="text-sm font-medium text-slate-400">Switch'i kapatırsanız o menü sidebar'da hiç görünmez</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="selectAllPerms()" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-emerald-600 hover:bg-emerald-100 transition-all">Tümünü Aç</button>
            <button type="button" onclick="deselectAllPerms()" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-rose-600 hover:bg-rose-100 transition-all">Tümünü Kapat</button>
        </div>
    </div>

    <div class="space-y-8" id="permission-modules">
        @foreach($modules as $mi => $mod)
            @php
                $modPerms = [];
                foreach ($mod['keys'] as $ki => $key) {
                    if (isset($permKeyToId[$key])) {
                        $modPerms[] = ['id'=>$permKeyToId[$key],'key'=>$key,'label'=>$mod['labels'][$ki] ?? $key];
                    }
                }
                if (empty($modPerms)) continue;
                // İlk key (.view) = master toggle key
                $masterPerm = $modPerms[0];
                $subPerms = array_slice($modPerms, 1);
                $masterChecked = in_array($masterPerm['id'], $selectedPerms);
                $activeCount = 0;
                foreach ($modPerms as $mp) { if (in_array($mp['id'], $selectedPerms)) $activeCount++; }
            @endphp

            @if(isset($categoryBreaks[$mi]))
                @if($mi > 0)<div class="border-t border-slate-100 pt-6"></div>@endif
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-px flex-1 bg-gradient-to-r from-indigo-200 to-transparent"></div>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-400 whitespace-nowrap">{{ $categoryBreaks[$mi] }}</span>
                    <div class="h-px flex-1 bg-gradient-to-l from-indigo-200 to-transparent"></div>
                </div>
            @endif

            @if($mi === 0 || isset($categoryBreaks[$mi]))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            @endif

            <div class="perm-module-card group relative" data-module="{{ $mi }}">
                <div class="module-trigger w-full rounded-[24px] border-2 transition-all duration-500 p-4 text-center {{ $masterChecked ? $mod['border'].' '.$mod['bg'].' shadow-lg '.$mod['shadow'] : 'border-slate-100 bg-slate-50/30 shadow-sm opacity-60' }}"
                     id="module-card-{{ $mi }}">

                    {{-- MASTER TOGGLE SWITCH + AÇIK/KAPALI LABEL --}}
                    <div class="flex justify-center mb-2" onclick="event.stopPropagation()">
                        <label class="relative inline-flex items-center gap-2 cursor-pointer select-none" title="Menüde göster/gizle">
                            <input type="checkbox" name="permissions[]" value="{{ $masterPerm['id'] }}"
                                   class="sr-only peer master-toggle" data-module="{{ $mi }}"
                                   id="master-toggle-{{ $mi }}"
                                   onchange="onMasterToggle({{ $mi }})"
                                   {{ $masterChecked ? 'checked' : '' }}>
                            <div class="relative w-14 h-7 bg-rose-400 rounded-full peer peer-checked:bg-emerald-500 peer-focus:ring-4 peer-focus:ring-emerald-100 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:rounded-full after:h-[22px] after:w-[22px] after:shadow-md after:transition-all after:duration-300 peer-checked:after:translate-x-[26px] shadow-inner transition-colors duration-300"></div>
                            <span class="text-[10px] font-black uppercase tracking-wider transition-colors duration-300 {{ $masterChecked ? 'text-emerald-600' : 'text-rose-500' }}" id="master-label-{{ $mi }}">{{ $masterChecked ? 'AÇIK' : 'KAPALI' }}</span>
                        </label>
                    </div>

                    {{-- 3D İkon --}}
                    <div class="mx-auto mb-2 flex h-14 w-14 items-center justify-center rounded-[18px] bg-gradient-to-br {{ $mod['gradient'] }} text-2xl shadow-xl {{ $mod['shadow'] }} transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3">
                        {{ $mod['icon'] }}
                    </div>

                    {{-- Modül Adı --}}
                    <h3 class="text-xs font-black text-slate-800 tracking-tight leading-tight">{{ $mod['title'] }}</h3>

                    {{-- Alt yetki butonu (sadece alt yetki varsa göster) --}}
                    @if(count($subPerms) > 0)
                        <button type="button" onclick="toggleModule({{ $mi }})" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 hover:text-indigo-600 transition-colors">
                            <span id="module-sub-count-{{ $mi }}">{{ $activeCount - ($masterChecked ? 1 : 0) }}/{{ count($subPerms) }}</span>
                            <svg class="w-3 h-3 transition-transform duration-300" id="module-chevron-{{ $mi }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    @endif
                </div>

                {{-- Alt Yetki Paneli --}}
                @if(count($subPerms) > 0)
                <div class="perm-detail-panel hidden absolute left-1/2 -translate-x-1/2 top-full mt-3 z-50 w-72 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_25px_60px_rgba(0,0,0,0.15)]"
                     id="module-panel-{{ $mi }}">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br {{ $mod['gradient'] }} text-xl shadow-md">{{ $mod['icon'] }}</div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900">{{ $mod['title'] }}</h4>
                                <p class="text-[10px] font-bold text-slate-400">Alt yetkileri yönetin</p>
                            </div>
                        </div>
                        <button type="button" onclick="toggleModule({{ $mi }})" class="h-8 w-8 rounded-lg bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition-colors">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <button type="button" onclick="toggleAllInModule({{ $mi }})" class="w-full mb-3 rounded-xl bg-slate-50 border border-slate-200 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all">Hepsini Seç / Kaldır</button>
                    <div class="space-y-2">
                        @foreach($subPerms as $sp)
                            <label class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3 transition-all hover:bg-white hover:border-slate-200 hover:shadow-sm cursor-pointer group/item">
                                <input type="checkbox" name="permissions[]" value="{{ $sp['id'] }}"
                                       data-module="{{ $mi }}" data-sub="1"
                                       onchange="updateSubCount({{ $mi }})"
                                       {{ in_array($sp['id'], $selectedPerms) ? 'checked' : '' }}
                                       class="perm-cb h-5 w-5 rounded-lg border-slate-300 {{ $mod['text'] }} focus:ring-2 focus:ring-offset-1 transition-all">
                                <span class="text-xs font-bold text-slate-600 group-hover/item:text-slate-900 transition-colors">{{ $sp['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            @php
                $nextCat = null;
                foreach ($categoryBreaks as $ci => $cl) { if ($ci > $mi) { $nextCat = $ci; break; } }
                $isLast = ($mi === count($modules) - 1);
                $isBefore = ($nextCat === $mi + 1);
            @endphp
            @if($isLast || $isBefore)</div>@endif
        @endforeach
    </div>
</div>

<div id="perm-overlay" class="fixed inset-0 z-40 hidden" onclick="closeAllPanels()"></div>

<style>
    .perm-detail-panel { z-index: 50; }
    .perm-module-card { position: relative; }
</style>

<script>
    let openModuleId = null;

    function onMasterToggle(moduleId) {
        const master = document.getElementById('master-toggle-' + moduleId);
        const card = document.getElementById('module-card-' + moduleId);
        const label = document.getElementById('master-label-' + moduleId);
        const subCbs = document.querySelectorAll(`input.perm-cb[data-module="${moduleId}"][data-sub="1"]`);

        if (master.checked) {
            card.classList.remove('opacity-60', 'border-slate-100', 'bg-slate-50/30', 'shadow-sm');
            if (label) { label.textContent = 'AÇIK'; label.classList.remove('text-rose-500'); label.classList.add('text-emerald-600'); }
        } else {
            subCbs.forEach(c => c.checked = false);
            card.classList.add('opacity-60');
            if (label) { label.textContent = 'KAPALI'; label.classList.remove('text-emerald-600'); label.classList.add('text-rose-500'); }
            closeAllPanels();
        }
        updateSubCount(moduleId);
    }

    function toggleModule(id) {
        // Master kapalıysa alt paneli açma
        const master = document.getElementById('master-toggle-' + id);
        if (!master.checked) return;

        const panel = document.getElementById('module-panel-' + id);
        if (!panel) return;
        const chevron = document.getElementById('module-chevron-' + id);
        const overlay = document.getElementById('perm-overlay');

        if (openModuleId === id) { closeAllPanels(); return; }
        closeAllPanels();
        panel.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        overlay.classList.remove('hidden');
        openModuleId = id;
    }

    function closeAllPanels() {
        document.querySelectorAll('.perm-detail-panel').forEach(p => p.classList.add('hidden'));
        document.querySelectorAll('[id^="module-chevron-"]').forEach(c => c.style.transform = '');
        document.getElementById('perm-overlay').classList.add('hidden');
        openModuleId = null;
    }

    function updateSubCount(moduleId) {
        const subCbs = document.querySelectorAll(`input.perm-cb[data-module="${moduleId}"][data-sub="1"]`);
        const el = document.getElementById('module-sub-count-' + moduleId);
        if (el && subCbs.length > 0) {
            const checked = [...subCbs].filter(c => c.checked).length;
            el.textContent = checked + '/' + subCbs.length;
        }
    }

    function toggleAllInModule(moduleId) {
        const cbs = document.querySelectorAll(`input.perm-cb[data-module="${moduleId}"][data-sub="1"]`);
        const allChecked = [...cbs].every(c => c.checked);
        cbs.forEach(c => c.checked = !allChecked);
        updateSubCount(moduleId);
    }

    function selectAllPerms() {
        document.querySelectorAll('.master-toggle').forEach(t => { t.checked = true; onMasterToggle(t.dataset.module); });
        document.querySelectorAll('input.perm-cb[data-sub="1"]').forEach(c => c.checked = true);
        document.querySelectorAll('.perm-module-card').forEach(card => {
            const id = card.dataset.module;
            if (id !== undefined) updateSubCount(id);
        });
    }

    function deselectAllPerms() {
        document.querySelectorAll('.master-toggle').forEach(t => { t.checked = false; onMasterToggle(t.dataset.module); });
    }
</script>
