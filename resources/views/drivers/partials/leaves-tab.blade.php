<div x-data="driverLeaves()" x-init="initData()" class="space-y-6">
    <div class="overflow-hidden rounded-[30px] border border-slate-200/70 bg-white shadow-lg shadow-slate-200/40">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">İzin Yönetimi</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Personelin ücretli/ücretsiz izin geçmişini görüntüleyin ve yeni izin tanımlayın.
                </p>
            </div>
            
            <button type="button" @click="openModal = true" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:scale-[1.02]">
                <span class="text-base">+</span>
                <span>Yeni İzin Ekle</span>
            </button>
        </div>

        <div class="p-6">
            <!-- Loading State -->
            <div x-show="loading" class="flex items-center justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
            </div>

            <!-- Error State -->
            <div x-show="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" x-text="error" style="display: none;"></div>

            <!-- Data Table -->
            <div x-show="!loading && !error && leaves" style="display: none;">
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-6 py-4">Başlangıç</th>
                                <th class="px-6 py-4">Bitiş</th>
                                <th class="px-6 py-4">İzin Türü</th>
                                <th class="px-6 py-4">Durum</th>
                                <th class="px-6 py-4">Notlar</th>
                                <th class="px-6 py-4 text-right">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="leave in leaves" :key="leave.id">
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-6 py-5 font-bold text-slate-900" x-text="formatDate(leave.start_date)"></td>
                                    <td class="px-6 py-5 font-bold text-slate-900" x-text="formatDate(leave.end_date)"></td>
                                    <td class="px-6 py-5">
                                        <span x-show="leave.leave_type === 'paid'" class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Ücretli</span>
                                        <span x-show="leave.leave_type === 'unpaid'" class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">Ücretsiz</span>
                                        <span x-show="leave.leave_type === 'sick'" class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">Raporlu/Hastalık</span>
                                        <span x-show="['paid','unpaid','sick'].indexOf(leave.leave_type) === -1" class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700" x-text="leave.leave_type"></span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span x-show="leave.status === 'approved'" class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700">Onaylandı</span>
                                        <span x-show="leave.status === 'pending'" class="inline-flex rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-700">Bekliyor</span>
                                        <span x-show="leave.status === 'rejected'" class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">Reddedildi</span>
                                    </td>
                                    <td class="px-6 py-5 text-xs" x-text="leave.notes || '-'"></td>
                                    <td class="px-6 py-5 text-right">
                                        <button @click="deleteLeave(leave.id)" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition">Sil</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!leaves.length">
                                <td colspan="6" class="p-8 text-center text-slate-500">Kayıtlı izin bulunmamaktadır.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div x-show="openModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6" style="display: none;">
        <div @click.away="openModal = false" x-transition class="w-full max-w-lg overflow-hidden rounded-[32px] border border-slate-200 bg-white shadow-2xl shadow-slate-900/20">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">İzin Ekle</h3>
                    <p class="mt-1 text-sm text-slate-500">Maaşı etkileyecek olan bu kaydı özenle oluşturun.</p>
                </div>
                <button type="button" @click="openModal = false" class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 transition hover:bg-slate-200">✕</button>
            </div>

            <form @submit.prevent="submitLeave" class="space-y-6 px-6 py-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Başlangıç</label>
                        <input type="date" x-model="form.start_date" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Bitiş</label>
                        <input type="date" x-model="form.end_date" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.14em] text-slate-400">İzin Türü</label>
                    <select x-model="form.leave_type" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500">
                        <option value="paid">Ücretli İzin (Maaş kesilmez)</option>
                        <option value="unpaid">Ücretsiz İzin (Maaştan düşülür)</option>
                        <option value="sick">Raporlu / Hastalık</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Not / Açıklama</label>
                    <textarea x-model="form.notes" rows="3" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-500" placeholder="İzin gerekçesi..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" @click="openModal = false" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Vazgeç</button>
                    <button type="submit" :disabled="submitting" class="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:scale-[1.02] disabled:opacity-50">
                        <span x-show="!submitting">Kaydet</span>
                        <span x-show="submitting">Kaydediliyor...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('driverLeaves', () => ({
        loading: true,
        error: null,
        leaves: [],
        driverId: {{ $driver->id }},
        openModal: false,
        submitting: false,
        form: {
            start_date: '',
            end_date: '',
            leave_type: 'paid',
            notes: ''
        },
        
        initData() {
            this.fetchLeaves();
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR');
        },

        async fetchLeaves() {
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch(`/api/v1/leaves?driver_id=${this.driverId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('İzinler yüklenemedi.');
                this.leaves = await response.json();
            } catch (err) {
                this.error = err.message;
            } finally {
                this.loading = false;
            }
        },

        async submitLeave() {
            this.submitting = true;
            try {
                const payload = {
                    driver_id: this.driverId,
                    start_date: this.form.start_date,
                    end_date: this.form.end_date,
                    leave_type: this.form.leave_type,
                    notes: this.form.notes
                };
                const response = await fetch(`/api/v1/leaves`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.message || 'İzin eklenirken hata oluştu.');
                }
                
                this.openModal = false;
                this.form = { start_date: '', end_date: '', leave_type: 'paid', notes: '' };
                this.fetchLeaves();
                // Opsiyonel olarak sayfada flash message gösterilebilir.
                alert('İzin başarıyla kaydedildi. Maaşlara anında yansıtılacaktır.');
            } catch (err) {
                alert(err.message);
            } finally {
                this.submitting = false;
            }
        },

        async deleteLeave(leaveId) {
            if (!confirm('Bu izin kaydını silmek istediğinize emin misiniz?')) return;
            
            try {
                const response = await fetch(`/api/v1/leaves/${leaveId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (!response.ok) throw new Error('Silinemedi.');
                
                this.fetchLeaves();
            } catch (err) {
                alert(err.message);
            }
        }
    }));
});
</script>
