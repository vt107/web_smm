@use('App\Support\Site')
{{-- CSS của Filament chỉ có class của Filament (không có h-8, gap-2... của Tailwind site) → kích thước logo đặt bằng style. --}}
<span class="notranslate text-gray-950 dark:text-white" translate="no" style="display: inline-flex; align-items: center; gap: .5rem; font-size: 1.125rem; font-weight: 700">
    <img src="{{ Site::logoUrl() }}" alt="{{ Site::name() }}" style="height: 2rem; width: auto; flex-shrink: 0">
    @if (Site::showName())
        <span>{{ Site::name() }}</span>
    @endif
</span>
