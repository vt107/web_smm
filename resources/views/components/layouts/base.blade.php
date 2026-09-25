@use('App\Support\Site')
@props([
    'title' => null,        // Tiêu đề trang, hiển thị "Tiêu đề · Tên web"
    'fullTitle' => null,    // Ghi đè toàn bộ <title> (landing page)
    'description' => null,
    'noindex' => false,     // Trang sau đăng nhập: không cho Google index
])
@php
    $pageTitle = $fullTitle ?? ($title ? $title.' · '.Site::name() : Site::name());
    $description ??= Site::get('description');
    $indexable = ! $noindex && Site::get('allow_indexing');
    $analyticsId = Site::get('google_analytics_id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ Site::faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#4f46e5">

    {{-- Chia sẻ lên Facebook / Telegram / Zalo / X --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ Site::name() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ Site::ogImageUrl() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ Site::ogImageUrl() }}">

    <script>
        // Gắn class .dark trước khi vẽ trang để không nháy màu (lựa chọn lưu ở localStorage bởi themeSwitcher).
        (() => {
            let theme = 'system';
            try { theme = localStorage.getItem('theme') || 'system'; } catch {}
            const dark = theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')

    @if ($analyticsId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            gtag('config', @js($analyticsId));
        </script>
    @endif
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    {{ $slot }}

    {{-- Toasts: window.toast(message, type) --}}
    <div x-data="{ items: [] }"
         @toast.window="const id = Date.now(); items.push({ id, ...$event.detail }); setTimeout(() => items = items.filter(i => i.id !== id), 4000)"
         class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
        <template x-for="item in items" :key="item.id">
            <div x-transition class="pointer-events-auto rounded-lg px-4 py-3 text-sm text-white shadow-lg"
                 :class="item.type === 'error' ? 'bg-rose-600' : 'bg-gray-900'" x-text="item.message"></div>
        </template>
    </div>

    {{-- Google Translate: chỉ nạp script khi khách chọn ngôn ngữ khác tiếng Anh (cookie googtrans). --}}
    <div id="google_translate_element" class="hidden"></div>
    <script>
        if (/(?:^|;\s*)googtrans=\/en\/[^;]+/.test(document.cookie)) {
            window.googleTranslateElementInit = () => new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
            const script = document.createElement('script');
            script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            document.body.appendChild(script);
        }
    </script>

    @stack('scripts')
</body>
</html>
