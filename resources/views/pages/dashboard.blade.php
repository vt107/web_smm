<x-layouts.app title="Tổng quan">
    <div x-data="dashboardPage()" x-init="init()" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card">
                <p class="text-sm text-slate-500">Số dư</p>
                <p class="mt-1 text-2xl font-bold" x-text="$store.me.data?.balance ?? '…'"></p>
                <a href="{{ route('add-funds') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">Nạp tiền →</a>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Đã chi tiêu</p>
                <p class="mt-1 text-2xl font-bold" x-text="$store.me.data?.total_spent ?? '…'"></p>
                <p class="mt-3 text-sm text-slate-500"><span x-text="stats.total ?? 0"></span> đơn · <span x-text="stats.active ?? 0"></span> đang chạy</p>
            </div>
            <div class="card">
                <p class="text-sm text-slate-500">Cấp bậc</p>
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
                            Chi thêm <b x-text="$store.me.data.next_level.remaining"></b> để lên
                            <b x-text="$store.me.data.next_level.name"></b> (giảm <span x-text="$store.me.data.next_level.discount"></span>%)
                        </p>
                    </div>
                </template>
            </div>
        </div>

        <div class="card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="card-title mb-0">Đơn hàng gần đây</h2>
                <a href="{{ route('new-order') }}" class="btn-primary btn-sm"><x-svg-icon name="plus" class="size-4" />Đặt đơn mới</a>
            </div>
            <div class="overflow-x-auto">
                <table class="tbl">
                    <thead><tr><th>ID</th><th>Dịch vụ</th><th>Số lượng</th><th>Tiền</th><th>Trạng thái</th><th>Ngày</th></tr></thead>
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
                        <tr x-show="loaded && recent.length === 0"><td colspan="6" class="py-8 text-center text-slate-500">Chưa có đơn nào.</td></tr>
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
