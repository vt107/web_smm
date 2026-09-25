<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    {{ $slot }}

    {{-- Thông báo nổi: window.toast(message, type) --}}
    <div x-data="{ items: [] }"
         @toast.window="const id = Date.now(); items.push({ id, ...$event.detail }); setTimeout(() => items = items.filter(i => i.id !== id), 4000)"
         class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-80 flex-col gap-2">
        <template x-for="item in items" :key="item.id">
            <div x-transition class="pointer-events-auto rounded-lg px-4 py-3 text-sm text-white shadow-lg"
                 :class="item.type === 'error' ? 'bg-red-600' : 'bg-slate-900'" x-text="item.message"></div>
        </template>
    </div>

    @stack('scripts')
</body>
</html>
