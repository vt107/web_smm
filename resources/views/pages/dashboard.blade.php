<x-layouts.app title="Dashboard">
    <div x-data="dashboardPage()" x-init="init()" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card">
                <p class="text-sm text-slate-500">Balance</p>
                <p class="mt-1 text-2xl font-bold" x-text="$store.me.data?.balance ?? '…'"></p>
                <a href="{{ route('add-funds') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">Add funds →</a>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Total spent</p>
                <p class="mt-1 text-2xl font-bold" x-text="$store.me.data?.total_spent ?? '…'"></p>
                <p class="mt-3 text-sm text-slate-500"><span x-text="stats.total ?? 0"></span> orders · <span x-text="stats.active ?? 0"></span> active</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Level</p>
                <p class="mt-1 text-2xl font-bold">
                    <span x-text="$store.me.data?.level.name"></span>
                    <span class="text-sm font-medium text-emerald-600" x-show="$store.me.data?.level.discount > 0"
                          x-text="'−' + $store.me.data?.level.discount + '%'"></span>
                </p>
                <template x-if="$store.me.data?.next_level">
                    <div class="mt-3">
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-indigo-500" :style="`width: ${$store.me.data.next_level.progress}%`"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            Spend <b x-text="$store.me.data.next_level.remaining"></b> more to reach
                            <b x-text="$store.me.data.next_level.name"></b> (<span x-text="$store.me.data.next_level.discount"></span>% off)
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <div class="card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="card-title mb-0">Recent orders</h2>
                <a href="{{ route('new-order') }}" class="btn-primary btn-sm"><x-svg-icon name="plus" class="size-4" />New order</a>
            </div>
            <div class="overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>ID</th><th>Service</th><th>Quantity</th><th>Charge</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <template x-for="order in recent" :key="order.id">
                            <tr>
                                <td x-text="order.id"></td>
                                <td class="max-w-xs truncate" x-text="order.service_name"></td>
                                <td x-text="order.quantity.toLocaleString()"></td>
                                <td x-text="order.charge"></td>
                                <td><span :class="'badge-' + order.status_color" x-text="order.status_label"></span></td>
                                <td class="whitespace-nowrap" x-text="order.created_at"></td>
                            </tr>
                        </template>
                        <tr x-show="loaded && recent.length === 0"><td colspan="6" class="py-8 text-center text-slate-500">No orders yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function dashboardPage() {
                return {
                    stats: {}, recent: [], loaded: false,
                    async init() {
                        this.$store.me.refresh();
                        const data = await api.get('/ajax/dashboard');
                        this.stats = data.stats;
                        this.recent = data.recent;
                        this.loaded = true;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
