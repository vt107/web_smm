@props(['platform'])
@php
    // Icon thương hiệu từ Simple Icons (CC0) trong public/images/platforms.
    $value = $platform instanceof \App\Enums\Platform ? $platform->value : (string) $platform;
    $slug = ['twitter' => 'x'][$value] ?? $value;
    $file = public_path("images/platforms/{$slug}.svg");
    $svg = is_file($file)
        ? preg_replace(['/<title>.*?<\/title>/', '/<svg /'], ['', '<svg fill="currentColor" aria-hidden="true" '], file_get_contents($file))
        : null;
@endphp
@if ($svg)
    <span {{ $attributes->merge(['class' => 'inline-flex [&>svg]:size-full']) }}>{!! $svg !!}</span>
@else
    <x-svg-icon name="grid" {{ $attributes }} />
@endif
