@use('App\Support\Site')
@props(['size' => 'h-8'])
{{-- Logo + tên web từ "Website settings" trong admin. --}}
<span {{ $attributes->merge(['class' => 'notranslate inline-flex items-center gap-2 font-bold']) }} translate="no">
    <img src="{{ Site::logoUrl() }}" alt="{{ Site::name() }}" class="{{ $size }} w-auto shrink-0">
    @if (Site::showName())
        <span>{{ Site::name() }}</span>
    @endif
</span>
