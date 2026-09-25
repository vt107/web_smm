<x-layouts.app title="Đơn hàng">
    <div x-data="ordersPage()" x-init="load()" class="card">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <template x-for="s in statuses" :key="s.value">
                <button :class="status === s.value ? 'tab-active' : 'tab'" @click="status = s.value; load()" x-text="s.label"></button>
            </template>
            <input class="input ml-auto max-w-xs" placeholder="Tìm theo ID hoặc link…" x-model.debounce.400ms="search" @input.debounce.400ms="load()">
        </div>

        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr><th>ID</th><th>Dịch vụ</th><th>Link</th><th>Số lượng</th><th>Bắt đầu</th><th>Còn lại</th><th>Tiền</th><th>Trạng thái</th><th>Ngày</th><th></th></tr>
                </thead>
                <tbody>
                    <template x-for="o in orders" :key="o.id">
                        <tr>
                            <td class="font-medium" x-text="o.id"></td>
                            <td class="max-w-56"><span class="line-clamp-2" x-text="o.service_name"></span></td>
                            <td class="max-w-48"><a :href="o.link" target="_blank" rel="noopener" class="block truncate text-indigo-600 hover:underline" x-text="o.link"></a></td>
                            <td x-text="o.quantity.toLocaleString()"></td>
                            <td x-text="o.start_count ?? '—'"></td>
                            <td x-text="o.remains ?? '—'"></td>
                            <td class="whitespace-nowrap">
                                <span x-text="o.charge"></span>
                                <span x-show="o.refunded" class="block text-xs text-emerald-600" x-text="'Hoàn ' + o.refunded"></span>
                            </td>
                            <td>
                                <span :class="'badge-' + o.status_color" x-text="o.status_label"></span>
                                <span x-show="o.cancel_requested" class="mt-1 block text-xs text-amber-600">Đang yêu cầu huỷ</span>
                                <span x-show="o.refilling" class="mt-1 block text-xs text-sky-600">Đang refill</span>
                            </td>
                            <td class="whitespace-nowrap" x-text="o.created_at"></td>
                            <td class="space-y-1 text-right whitespace-nowrap">
                                <button x-show="o.can_refill" class="btn-secondary btn-sm" @click="action(o, 'refill')">Refill</button>
                                <button x-show="o.can_cancel" class="btn-danger btn-sm" @click="action(o, 'cancel')">Huỷ</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && orders.length === 0"><td colspan="10" class="py-10 text-center text-slate-500">Không có đơn nào.</td></tr>
                </tbody>
            </table>
        </div>
        <x-pagination />
    </div>

    @push('scripts')
        <script>
            function ordersPage() {
                return {
                    orders: [], meta: null, loading: true, status: '', search: '',
                    statuses: [
                        { value: '', label: 'Tất cả' }, { value: 'pending', label: 'Chờ xử lý' },
                        { value: 'in_progress', label: 'Đang chạy' }, { value: 'completed', label: 'Hoàn thành' },
                        { value: 'partial', label: 'Một phần' }, { value: 'canceled', label: 'Đã huỷ' },
                    ],
                    async load(page = 1) {
                        this.loading = true;
                        const res = await api.get('/ajax/orders', { page, status: this.status, search: this.search });
                        this.orders = res.data;
                        this.meta = res.meta;
                        this.loading = false;
                    },
                    async action(order, type) {
                        const question = type === 'cancel' ? `Huỷ đơn #${order.id}?` : `Yêu cầu refill đơn #${order.id}?`;
                        if (!confirm(question)) return;
                        try {
                            const res = await api.post(`/ajax/orders/${order.id}/${type}`);
                            toast(res.message);
                            this.load(this.meta?.current_page ?? 1);
                        } catch (e) {
                            toast(e.message, 'error');
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
