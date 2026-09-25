<x-layouts.app title="Transactions">
    <div x-data="transactionsPage()" x-init="load()" class="card">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Time</th><th>Type</th><th>Description</th><th class="text-right">Amount</th><th class="text-right">Balance after</th></tr></thead>
                <tbody>
                    <template x-for="t in items" :key="t.id">
                        <tr>
                            <td class="whitespace-nowrap" x-text="t.created_at"></td>
                            <td><span :class="'badge-' + t.type_color" x-text="t.type_label"></span></td>
                            <td x-text="t.description ?? ''"></td>
                            <td class="text-right font-medium whitespace-nowrap" :class="t.positive ? 'text-emerald-600' : 'text-red-600'" x-text="t.amount"></td>
                            <td class="text-right whitespace-nowrap" x-text="t.balance_after"></td>
                        </tr>
                    </template>
                    <tr x-show="loaded && items.length === 0"><td colspan="5" class="py-10 text-center text-slate-500">No transactions yet.</td></tr>
                </tbody>
            </table>
        </div>
        <x-pagination />
    </div>

    @push('scripts')
        <script>
            function transactionsPage() {
                return {
                    items: [], meta: null, loaded: false,
                    async load(page = 1) {
                        const res = await api.get('/ajax/transactions', { page });
                        this.items = res.data;
                        this.meta = res.meta;
                        this.loaded = true;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
