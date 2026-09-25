<x-layouts.app title="Support">
    <div x-data="ticketsPage()" x-init="load()" class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <h2 class="card-title">Your tickets</h2>
            <div class="divide-y divide-slate-100">
                <template x-for="t in tickets" :key="t.id">
                    <a :href="'/tickets/' + t.id" class="flex items-center gap-4 py-3 hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium" x-text="'#' + t.id + ' · ' + t.subject"></p>
                            <p class="text-xs text-slate-500" x-text="t.category_label + ' · updated ' + t.last_reply_at"></p>
                        </div>
                        <span :class="'badge-' + t.status_color" x-text="t.status_label"></span>
                    </a>
                </template>
                <p x-show="loaded && tickets.length === 0" class="py-8 text-center text-slate-500">No tickets yet.</p>
            </div>
            <x-pagination />
        </div>

        <form class="card h-fit space-y-4" @submit.prevent="create()">
            <h2 class="card-title">New ticket</h2>
            <div>
                <label class="label">Topic</label>
                <select class="input" x-model="form.category">
                    @foreach (\App\Enums\TicketCategory::cases() as $category)
                        <option value="{{ $category->value }}">{{ $category->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="needsOrders">
                <label class="label">Order IDs</label>
                <input class="input" x-model="form.order_ids" placeholder="e.g. 123, 124">
            </div>
            <div>
                <label class="label">Subject</label>
                <input class="input" x-model="form.subject" required maxlength="255">
            </div>
            <div>
                <label class="label">Message</label>
                <textarea class="input" rows="5" x-model="form.message" required></textarea>
            </div>
            <div>
                <label class="label">Attachments (up to 3 images, 2 MB each)</label>
                <input type="file" accept="image/*" multiple x-ref="files" class="text-sm">
            </div>
            <button class="btn-primary w-full" :disabled="busy">Submit ticket</button>
        </form>
    </div>

    @push('scripts')
        <script>
            function ticketsPage() {
                return {
                    tickets: [], meta: null, loaded: false, busy: false,
                    form: { category: 'refill', order_ids: '', subject: '', message: '' },
                    get needsOrders() {
                        return ['refill', 'cancel', 'speed_up'].includes(this.form.category);
                    },
                    async load(page = 1) {
                        const res = await api.get('/ajax/tickets', { page });
                        this.tickets = res.data;
                        this.meta = res.meta;
                        this.loaded = true;
                    },
                    async create() {
                        this.busy = true;
                        const data = new FormData();
                        Object.entries(this.form).forEach(([k, v]) => data.append(k, v));
                        [...this.$refs.files.files].forEach(f => data.append('attachments[]', f));
                        try {
                            const res = await api('POST', '/ajax/tickets', data);
                            toast(res.message);
                            window.location.href = '/tickets/' + res.ticket.id;
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
