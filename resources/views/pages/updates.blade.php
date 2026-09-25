<x-layouts.app title="Cập nhật dịch vụ">
    <div x-data="updatesPage()" x-init="load()" class="card">
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Thời gian</th><th>Dịch vụ</th><th>Thay đổi</th><th>Chi tiết</th></tr></thead>
                <tbody>
                    <template x-for="u in items" :key="u.id">
                        <tr>
                            <td class="whitespace-nowrap" x-text="u.created_at"></td>
                            <td><span class="text-slate-400" x-text="'#' + u.service_id + ' '"></span><span x-text="u.service_name"></span></td>
                            <td><span :class="'badge-' + u.type_color" x-text="u.type_label"></span></td>
                            <td class="whitespace-nowrap">
                                <span x-show="u.old_value" class="text-slate-400 line-through" x-text="u.old_value"></span>
                                <span x-show="u.new_value" class="ml-1 font-medium" x-text="u.new_value"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="loaded && items.length === 0"><td colspan="4" class="py-10 text-center text-slate-500">Chưa có cập nhật.</td></tr>
                </tbody>
            </table>
        </div>
        <x-pagination />
    </div>

    @push('scripts')
        <script>
            function updatesPage() {
                return {
                    items: [], meta: null, loaded: false,
                    async load(page = 1) {
                        const res = await api.get('/ajax/updates', { page });
                        this.items = res.data;
                        this.meta = res.meta;
                        this.loaded = true;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
