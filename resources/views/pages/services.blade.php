<x-layouts.app title="Bảng giá dịch vụ">
    <div x-data="servicesPage()" x-init="init()" class="space-y-4">
        <div class="card flex flex-wrap items-center gap-3">
            <input class="input max-w-sm" placeholder="Tìm dịch vụ…" x-model.debounce.200ms="search">
            <select class="input max-w-48" x-model="platform">
                <option value="">Tất cả nền tảng</option>
                <template x-for="p in platforms" :key="p.value"><option :value="p.value" x-text="p.label"></option></template>
            </select>
            <p class="ml-auto text-sm text-slate-500">Giá đã áp dụng giảm giá theo cấp bậc của bạn.</p>
        </div>

        <template x-for="c in filtered" :key="c.id">
            <div class="card overflow-x-auto p-0">
                <h2 class="border-b border-slate-200 px-5 py-3 font-semibold" x-text="c.name"></h2>
                <table class="tbl">
                    <thead><tr><th class="w-16">ID</th><th>Dịch vụ</th><th>Giá / 1000</th><th>Min – Max</th><th>Refill</th><th>Huỷ</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="s in c.services" :key="s.id">
                            <tr>
                                <td x-text="s.id"></td>
                                <td>
                                    <span x-text="s.name"></span>
                                    <details x-show="s.description" class="mt-1 text-xs text-slate-500">
                                        <summary class="cursor-pointer">Mô tả</summary>
                                        <p class="mt-1 whitespace-pre-line" x-text="s.description"></p>
                                    </details>
                                </td>
                                <td class="font-medium whitespace-nowrap" x-text="s.rate + (s.is_package ? ' / gói' : '')"></td>
                                <td class="whitespace-nowrap" x-text="s.min.toLocaleString() + ' – ' + s.max.toLocaleString()"></td>
                                <td x-text="s.refill ? (s.refill_days ? s.refill_days + ' ngày' : '✓') : '—'"></td>
                                <td x-text="s.cancel ? '✓' : '—'"></td>
                                <td class="text-right"><a class="btn-primary btn-sm" :href="'{{ route('new-order') }}?service=' + s.id">Đặt</a></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
        <p x-show="loaded && filtered.length === 0" class="card text-center text-slate-500">Không tìm thấy dịch vụ.</p>
    </div>

    @push('scripts')
        <script>
            function servicesPage() {
                return {
                    categories: [], loaded: false, search: '', platform: '',
                    async init() {
                        this.categories = (await api.get('/ajax/services')).categories;
                        this.loaded = true;
                    },
                    get platforms() {
                        const seen = new Map();
                        this.categories.forEach(c => seen.set(c.platform, c.platform_label));
                        return [...seen].map(([value, label]) => ({ value, label }));
                    },
                    get filtered() {
                        const q = this.search.trim().toLowerCase();
                        return this.categories
                            .filter(c => !this.platform || c.platform === this.platform)
                            .map(c => ({ ...c, services: q ? c.services.filter(s => String(s.id) === q || s.name.toLowerCase().includes(q)) : c.services }))
                            .filter(c => c.services.length);
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
