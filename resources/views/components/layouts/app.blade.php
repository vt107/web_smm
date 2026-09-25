@php
    $user = auth()->user()->loadMissing(['level', 'displayCurrency']);
    $nav = [
        ['dashboard', 'Dashboard', 'home'],
        ['new-order', 'New order', 'plus'],
        ['orders', 'Orders', 'list'],
        ['services', 'Services', 'grid'],
        ['add-funds', 'Add funds', 'wallet'],
        ['transactions', 'Transactions', 'receipt'],
        ['tickets', 'Support', 'lifebuoy'],
        ['updates', 'Updates', 'bell'],
        ['api-docs', 'API', 'code'],
        ['account', 'Account', 'user'],
    ];
@endphp
<x-layouts.base :title="$title ?? null" noindex>
    <div x-data="{ sidebar: false }" class="min-h-screen lg:pl-64">
        {{-- Sidebar --}}
        <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-gray-900/60 lg:hidden"></div>
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-surface transition-transform">
            <a href="{{ route('home') }}" class="flex h-16 items-center border-b border-slate-200 px-6 text-lg text-slate-900">
                <x-logo />
            </a>
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                @foreach ($nav as [$route, $label, $icon])
                    <a href="{{ route($route) }}"
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                           'bg-indigo-50 text-indigo-700' => request()->routeIs($route, $route.'.*'),
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs($route, $route.'.*'),
                       ])>
                        <x-svg-icon :name="$icon" />{{ $label }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200 p-3">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                    <x-svg-icon name="logout" />Log out
                </button>
            </form>
        </aside>

        {{-- Header --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-surface/90 px-4 backdrop-blur lg:px-8">
            <button class="rounded-lg p-1 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-gray-100 dark:hover:bg-gray-700" @click="sidebar = true" aria-label="Open menu"><x-svg-icon name="menu" class="size-6" /></button>
            <h1 class="flex-1 truncate text-lg font-semibold">{{ $title ?? '' }}</h1>
            <div class="hidden md:block"><x-preferences /></div>
            <a href="{{ route('add-funds') }}" class="notranslate flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200 dark:bg-gray-800 dark:text-white dark:ring-1 dark:ring-gray-600 dark:hover:bg-gray-700" translate="no">
                <x-svg-icon name="wallet" class="size-4 text-slate-500 dark:text-emerald-400" />
                <span class="font-semibold" x-data x-text="$store.me.data?.balance ?? @js($user->displayCurrency->format($user->balance, roundUp: false))"></span>
            </a>
            <span class="notranslate hidden rounded-full px-2.5 py-1 text-xs font-semibold text-white sm:inline" translate="no"
                  style="background-color: {{ $user->level->color ?: '#6366f1' }}">{{ $user->level->name }}</span>
            <span class="notranslate hidden text-sm text-slate-600 xl:inline dark:text-gray-200" translate="no">{{ $user->username }}</span>
        </header>

        <main class="mx-auto max-w-7xl p-4 lg:p-8">
            <div class="mb-4 md:hidden"><x-preferences /></div>
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
