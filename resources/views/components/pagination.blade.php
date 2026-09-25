{{-- Phân trang cho dữ liệu JSON dạng Laravel paginator: cần biến meta + hàm load(page) trong x-data. --}}
<div class="flex items-center justify-between px-1 pt-4 text-sm text-slate-600" x-show="meta && meta.last_page > 1" x-cloak>
    <span>Trang <span x-text="meta?.current_page"></span> / <span x-text="meta?.last_page"></span></span>
    <div class="flex gap-2">
        <button class="btn-secondary btn-sm" :disabled="meta?.current_page <= 1" @click="load(meta.current_page - 1)">← Trước</button>
        <button class="btn-secondary btn-sm" :disabled="meta?.current_page >= meta?.last_page" @click="load(meta.current_page + 1)">Sau →</button>
    </div>
</div>
