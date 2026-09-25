<x-layouts.app title="Services">
    <div x-data="servicesPage()" x-init="init()" class="space-y-4">
        <div class="card flex flex-wrap items-center gap-3">
            <input class="input max-w-sm" placeholder="Search services…" x-model.debounce.300ms="search">
            <select class="input max-w-48" x-model="platform" :disabled="results !== null">
                <option value="">All platforms</option>
                <template x-for="p in platforms" :key="p.value"><option :value="p.value" x-text="p.label"></option></template>
            </select>
            <p class="ml-auto text-sm text-slate-500">Prices include your level discount.</p>
        </div>

        {{-- Kết quả tìm kiếm --}}
        <template x-if="results !== null">
            <div class="card overflow-x-auto p-0">
                <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">
                    <span x-text="results.length"></span> matching services
                    <button class="ml-2 text-sm font-medium text-indigo-600 hover:underline" @click="search = ''">Clear</button>
                </h2>
                <table class="tbl">
                    <thead><tr><th class="w-16">ID</th><th>Service</th><th>Rate / 1000</th><th>Min – Max</th><th>Refill</th><th>Cancel</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="s in results" :key="s.id">
                            <tr>
                                <td x-text="s.id"></td>
                                <td><span x-text="s.name"></span><span class="block text-xs text-slate-500" x-text="s.category_name"></span></td>
                                <td class="font-medium whitespace-nowrap" x-text="s.rate + (s.is_package ? ' / package' : '')"></td>
                                <td class="whitespace-nowrap" x-text="s.min.toLocaleString() + ' – ' + s.max.toLocaleString()"></td>
                                <td x-text="s.refill ? (s.refill_days ? s.refill_days + ' days' : '✓') : '—'"></td>
                                <td x-text="s.cancel ? '✓' : '—'"></td>
                                <td class="text-right"><a class="btn-primary btn-sm" :href="'{{ route('new-order') }}?service=' + s.id">Order</a></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        {{-- Danh mục: bấm để tải dịch vụ --}}
        <template x-for="c in filtered" :key="c.id">
            <div x-show="results === null" class="card overflow-x-auto p-0">
                <button type="button" class="flex w-full items-center justify-between px-5 py-3 text-left font-semibold" @click="toggle(c.id)">
                    <span x-text="c.name"></span>
                    <span class="text-sm font-normal text-slate-500">
                        <span x-text="c.service_count"></span> services <span x-text="open[c.id] ? '▴' : '▾'"></span>
                    </span>
                </button>
                <template x-if="open[c.id]">
                    <div class="border-t border-slate-200">
                        <p x-show="!services[c.id]" class="px-5 py-4 text-sm text-slate-500">Loading…</p>
                        <table x-show="services[c.id]" class="tbl">
                            <thead><tr><th class="w-16">ID</th><th>Service</th><th>Rate / 1000</th><th>Min – Max</th><th>Refill</th><th>Cancel</th><th></th></tr></thead>
                            <tbody>
                                <template x-for="s in services[c.id] ?? []" :key="s.id">
                                    <tr>
                                        <td x-text="s.id"></td>
                                        <td>
                                            <span x-text="s.name"></span>
                                            <details x-show="s.description" class="mt-1 text-xs text-slate-500">
                                                <summary>Description</summary>
                                                <p class="mt-1 whitespace-pre-line" x-text="s.description"></p>
                                            </details>
                                        </td>
                                        <td class="font-medium whitespace-nowrap" x-text="s.rate + (s.is_package ? ' / package' : '')"></td>
                                        <td class="whitespace-nowrap" x-text="s.min.toLocaleString() + ' – ' + s.max.toLocaleString()"></td>
                                        <td x-text="s.refill ? (s.refill_days ? s.refill_days + ' days' : '✓') : '—'"></td>
                                        <td x-text="s.cancel ? '✓' : '—'"></td>
                                        <td class="text-right"><a class="btn-primary btn-sm" :href="'{{ route('new-order') }}?service=' + s.id">Order</a></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </template>
        <p x-show="loaded && results === null && filtered.length === 0" class="card text-center text-slate-500">No services found.</p>
    </div>

    @push('scripts')
        <script>
            function servicesPage() {
                return {
                    categories: [], services: {}, open: {}, results: null, loaded: false, search: '', platform: '',
                    async init() {
                        this.categories = (await api.get('/ajax/catalog')).categories;
                        this.loaded = true;
                        // Mở sẵn danh mục đầu tiên.
                        if (this.categories[0]) this.toggle(this.categories[0].id);
                        this.$watch('search', async (value) => {
                            const q = value.trim();
                            this.results = q ? (await api.get('/ajax/catalog/search', { q })).services : null;
                        });
                    },
                    get platforms() {
                        const seen = new Map();
                        this.categories.forEach(c => seen.set(c.platform, c.platform_label));
                        return [...seen].map(([value, label]) => ({ value, label }));
                    },
                    get filtered() {
                        return this.categories.filter(c => !this.platform || c.platform === this.platform);
                    },
                    async toggle(id) {
                        this.open[id] = !this.open[id];
                        if (this.open[id] && !this.services[id]) {
                            this.services[id] = (await api.get(`/ajax/catalog/categories/${id}`)).services;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
