<x-layouts.app title="New order">
    <div x-data="newOrderPage()" x-init="init()" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="flex gap-2">
                <button :class="tab === 'single' ? 'tab-active' : 'tab'" @click="tab = 'single'">Single order</button>
                <button :class="tab === 'mass' ? 'tab-active' : 'tab'" @click="tab = 'mass'">Mass order</button>
            </div>

            {{-- Đơn lẻ --}}
            <form x-show="tab === 'single'" class="card space-y-4" @submit.prevent="submit()">
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="platform === '' ? 'tab-active' : 'tab'" @click="platform = ''; resetCategory()">All</button>
                    <template x-for="p in platforms" :key="p.value">
                        <button type="button" :class="platform === p.value ? 'tab-active' : 'tab'" @click="platform = p.value; resetCategory()" x-text="p.label"></button>
                    </template>
                </div>

                <div>
                    <label class="label">Search services</label>
                    <input class="input" x-model.debounce.200ms="search" placeholder="Service name or ID…">
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="label">Category</label>
                        <select class="input" x-model.number="categoryId" @change="selectFirstService()">
                            <template x-for="c in filteredCategories" :key="c.id">
                                <option :value="c.id" x-text="c.name" :selected="c.id === categoryId"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="label">Service</label>
                        <select class="input" x-model.number="serviceId" @change="resetForm()">
                            <template x-for="s in categoryServices" :key="s.id">
                                <option :value="s.id" x-text="`#${s.id} · ${s.name} — ${s.rate}`" :selected="s.id === serviceId"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <template x-if="service">
                    <div class="space-y-4">
                        <div>
                            <label class="label">Link</label>
                            <input class="input" x-model="form.link" required placeholder="https://…">
                        </div>

                        <template x-for="field in service.fields" :key="field.name">
                            <div>
                                <label class="label" x-text="field.label + (field.required ? '' : ' (optional)')"></label>
                                <template x-if="field.input === 'textarea'">
                                    <textarea class="input" rows="5" x-model="form.extra[field.name]" :required="field.required"></textarea>
                                </template>
                                <template x-if="field.input !== 'textarea'">
                                    <input class="input" :type="field.input" x-model="form.extra[field.name]" :required="field.required">
                                </template>
                                <p class="hint" x-show="field.hint" x-text="field.hint"></p>
                            </div>
                        </template>

                        <div x-show="needsQuantity">
                            <label class="label">Quantity</label>
                            <input class="input" type="number" x-model.number="form.quantity" :min="service.min" :max="service.max">
                            <p class="hint">Min. <span x-text="service.min.toLocaleString()"></span> · max. <span x-text="service.max.toLocaleString()"></span></p>
                        </div>
                        <p x-show="service.lines_field" class="hint">
                            Quantity = number of lines: <b x-text="quantity"></b>
                            (min. <span x-text="service.min"></span>, max. <span x-text="service.max"></span>)
                        </p>

                        <div x-show="service.dripfeed" class="rounded-lg border border-slate-200 p-3">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" x-model="form.dripfeed" class="rounded border-slate-300"> Drip-feed (deliver in several runs)
                            </label>
                            <div x-show="form.dripfeed" class="mt-3 grid gap-3 md:grid-cols-2">
                                <div>
                                    <label class="label">Runs</label>
                                    <input class="input" type="number" min="2" x-model.number="form.runs">
                                </div>
                                <div>
                                    <label class="label">Interval (minutes)</label>
                                    <input class="input" type="number" min="1" x-model.number="form.interval">
                                </div>
                                <p class="hint md:col-span-2">Total quantity: <b x-text="totalQuantity.toLocaleString()"></b></p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                            <div>
                                <p class="text-sm text-slate-500">Estimated charge</p>
                                <p class="text-xl font-bold" x-text="estimate"></p>
                            </div>
                            <button class="btn-primary" :disabled="submitting">
                                <span x-text="submitting ? 'Placing…' : 'Place order'"></span>
                            </button>
                        </div>
                    </div>
                </template>
                <p x-show="loaded && !service" class="text-sm text-slate-500">No matching services found.</p>
            </form>

            {{-- Mass order --}}
            <div x-show="tab === 'mass'" x-cloak class="card space-y-4">
                <div>
                    <label class="label">One order per line: <code class="rounded bg-slate-100 px-1">service_id | link | quantity</code></label>
                    <textarea class="input font-mono" rows="10" x-model="mass" placeholder="1 | https://instagram.com/abc | 1000&#10;2 | https://tiktok.com/@xyz | 500"></textarea>
                </div>
                <button class="btn-primary" :disabled="submitting || !mass.trim()" @click="submitMass()">Place all orders</button>
                <template x-if="massResults.length">
                    <table class="tbl">
                        <thead><tr><th>Line</th><th>Result</th></tr></thead>
                        <tbody>
                            <template x-for="r in massResults" :key="r.line">
                                <tr>
                                    <td x-text="r.line"></td>
                                    <td>
                                        <span x-show="r.order" class="text-emerald-600" x-text="'Order #' + r.order + ' placed'"></span>
                                        <span x-show="r.error" class="text-red-600" x-text="r.error"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>
            </div>
        </div>

        {{-- Thông tin dịch vụ --}}
        <div class="space-y-4">
            <div class="card" x-show="service" x-cloak>
                <h2 class="card-title" x-text="service?.name"></h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Service ID</dt><dd class="font-medium" x-text="'#' + service?.id"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500" x-text="service?.is_package ? 'Price / package' : 'Rate / 1000'"></dt><dd class="font-medium" x-text="service?.rate"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Type</dt><dd x-text="service?.type_label"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Min / Max</dt><dd x-text="service ? service.min.toLocaleString() + ' / ' + service.max.toLocaleString() : ''"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Average time</dt><dd x-text="service?.average_time ? formatMinutes(service.average_time) : 'Not enough data'"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Refill</dt><dd x-text="service?.refill ? (service.refill_days ? service.refill_days + ' days' : 'Yes') : 'No'"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cancel</dt><dd x-text="service?.cancel ? 'Yes' : 'No'"></dd></div>
                </dl>
                <p x-show="service?.description" class="mt-4 border-t border-slate-100 pt-4 text-sm whitespace-pre-line text-slate-600" x-text="service?.description"></p>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function newOrderPage() {
                return {
                    tab: 'single', loaded: false, submitting: false,
                    categories: [], currency: null,
                    platform: '', search: '', categoryId: null, serviceId: null,
                    form: { link: '', quantity: null, extra: {}, dripfeed: false, runs: 2, interval: 30 },
                    mass: '', massResults: [],

                    async init() {
                        const data = await api.get('/ajax/services');
                        this.categories = data.categories;
                        this.currency = data.currency;
                        this.loaded = true;

                        const preset = Number(new URLSearchParams(location.search).get('service'));
                        const found = preset && this.categories.find(c => c.services.some(s => s.id === preset));
                        if (found) {
                            this.categoryId = found.id;
                            this.serviceId = preset;
                        } else {
                            this.resetCategory();
                        }
                        this.resetForm();
                        this.$watch('search', () => this.resetCategory());
                    },

                    get platforms() {
                        const seen = new Map();
                        this.categories.forEach(c => seen.set(c.platform, c.platform_label));
                        return [...seen].map(([value, label]) => ({ value, label }));
                    },
                    get filteredCategories() {
                        const q = this.search.trim().toLowerCase();
                        return this.categories
                            .filter(c => !this.platform || c.platform === this.platform)
                            .map(c => ({ ...c, services: q ? c.services.filter(s => String(s.id) === q || s.name.toLowerCase().includes(q)) : c.services }))
                            .filter(c => c.services.length);
                    },
                    get categoryServices() {
                        return this.filteredCategories.find(c => c.id === this.categoryId)?.services ?? [];
                    },
                    get service() {
                        return this.categoryServices.find(s => s.id === this.serviceId) ?? null;
                    },
                    get needsQuantity() {
                        return this.service && !this.service.is_package && !this.service.lines_field;
                    },
                    get quantity() {
                        if (!this.service) return 0;
                        if (this.service.is_package) return 1;
                        if (this.service.lines_field) {
                            return String(this.form.extra[this.service.lines_field] ?? '').split('\n').filter(l => l.trim()).length;
                        }
                        return Number(this.form.quantity) || 0;
                    },
                    get totalQuantity() {
                        return this.quantity * (this.form.dripfeed ? Number(this.form.runs) || 1 : 1);
                    },
                    get estimate() {
                        if (!this.service) return '—';
                        const usd = this.service.is_package ? Number(this.service.rate_usd) : Number(this.service.rate_usd) * this.totalQuantity / 1000;
                        return formatMoney(usd, this.currency);
                    },

                    resetCategory() {
                        this.categoryId = this.filteredCategories[0]?.id ?? null;
                        this.selectFirstService();
                    },
                    selectFirstService() {
                        this.serviceId = this.categoryServices[0]?.id ?? null;
                        this.resetForm();
                    },
                    resetForm() {
                        this.form.extra = {};
                        this.form.quantity = this.service?.min ?? null;
                        this.form.dripfeed = false;
                    },
                    formatMinutes(m) {
                        return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`;
                    },

                    async submit() {
                        this.submitting = true;
                        try {
                            const payload = { service: this.service.id, link: this.form.link, ...this.form.extra };
                            if (this.needsQuantity) payload.quantity = this.form.quantity;
                            if (this.form.dripfeed) Object.assign(payload, { runs: this.form.runs, interval: this.form.interval });

                            const res = await api.post('/ajax/orders', payload);
                            toast(res.message);
                            this.$store.me.refresh();
                            this.form.link = '';
                            this.resetForm();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.submitting = false;
                        }
                    },

                    async submitMass() {
                        this.submitting = true;
                        try {
                            const res = await api.post('/ajax/orders/mass', { orders: this.mass });
                            this.massResults = res.results;
                            toast(res.message);
                            this.$store.me.refresh();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.submitting = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
