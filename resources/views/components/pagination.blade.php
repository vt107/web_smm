{{-- Pagination for Laravel paginator JSON: needs `meta` and `load(page)` in the parent x-data. --}}
<div class="flex items-center justify-between px-1 pt-4 text-sm text-slate-600" x-show="meta && meta.last_page > 1" x-cloak>
    <span>Page <span x-text="meta?.current_page"></span> of <span x-text="meta?.last_page"></span></span>
    <div class="flex gap-2">
        <button class="btn-secondary btn-sm" :disabled="meta?.current_page <= 1" @click="load(meta.current_page - 1)">← Previous</button>
        <button class="btn-secondary btn-sm" :disabled="meta?.current_page >= meta?.last_page" @click="load(meta.current_page + 1)">Next →</button>
    </div>
</div>
