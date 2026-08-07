<div x-data="customerStatement()" x-init="initData()" class="space-y-6">
    <!-- Filters -->
    <div class="overflow-hidden rounded-[30px] border border-slate-200/70 bg-white shadow-lg shadow-slate-200/40">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Detaylı Cari Ekstre</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Hangi araç, hangi şoför, hangi gün, kime gitti? Maliyet analizi ve detaylı döküm.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <input type="month" x-model="selectedPeriod" @change="fetchStatement()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                <button type="button" @click="fetchStatement()" class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/10 transition hover:bg-slate-800">
                    <span>🔄</span>
                    <span>Yenile</span>
                </button>
            </div>
        </div>

        <div class="p-6">
            <!-- Loading State -->
            <div x-show="loading" class="flex items-center justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
            </div>

            <!-- Error State -->
            <div x-show="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" x-text="error" style="display: none;"></div>

            <!-- Data Table -->
            <div x-show="!loading && !error && statementData" style="display: none;" class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Seçili Dönem</div>
                        <div class="mt-2 text-xl font-black text-slate-900" x-text="statementData?.period"></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-indigo-50 p-5">
                        <div class="text-xs font-bold uppercase tracking-wider text-indigo-500">Toplam Sefer Sayısı</div>
                        <div class="mt-2 text-xl font-black text-indigo-900" x-text="statementData?.details?.length || 0"></div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <div class="text-xs font-bold uppercase tracking-wider text-emerald-600">Toplam Hakediş</div>
                        <div class="mt-2 text-2xl font-black text-emerald-700" x-text="formatCurrency(statementData?.total_cost)"></div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Tarih</th>
                                <th class="px-4 py-3">Güzergah</th>
                                <th class="px-4 py-3">Araç Plakası</th>
                                <th class="px-4 py-3">Sabah Şoförü</th>
                                <th class="px-4 py-3">Akşam Şoförü</th>
                                <th class="px-4 py-3 text-right">Tutar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="item in statementData?.details" :key="item.id">
                                <tr class="hover:bg-slate-50/50">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900" x-text="item.date"></td>
                                    <td class="px-4 py-3" x-text="item.route_name"></td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700" x-text="item.vehicle_plate"></span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700" x-text="item.morning_driver"></td>
                                    <td class="px-4 py-3 text-slate-700" x-text="item.evening_driver"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-emerald-600" x-text="formatCurrency(item.cost)"></td>
                                </tr>
                            </template>
                            <tr x-show="!statementData?.details?.length">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Bu dönemde herhangi bir sefer kaydı bulunamadı.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 font-bold text-slate-900" x-show="statementData?.details?.length > 0">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right">Genel Toplam:</td>
                                <td class="px-4 py-3 text-right text-emerald-700 text-base" x-text="formatCurrency(statementData?.total_cost)"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('customerStatement', () => ({
        loading: true,
        error: null,
        statementData: null,
        customerId: {{ $customer->id }},
        selectedPeriod: new Date().toISOString().slice(0, 7), // YYYY-MM format
        
        initData() {
            this.fetchStatement();
        },
        
        formatCurrency(value) {
            if (value === undefined || value === null) return '0,00 ₺';
            return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value);
        },

        async fetchStatement() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch(`/api/v1/customers/${this.customerId}/statement?period=${this.selectedPeriod}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Veriler alınırken bir hata oluştu.');
                }
                
                const data = await response.json();
                this.statementData = data;
            } catch (err) {
                this.error = err.message || 'Bilinmeyen bir hata oluştu.';
                this.statementData = null;
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>
