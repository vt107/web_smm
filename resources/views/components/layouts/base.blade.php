<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
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
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
