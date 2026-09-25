@php
    $user = auth()->user()->loadMissing(['level', 'displayCurrency']);
    $nav = [
        ['dashboard', 'Tổng quan', 'home'],
        ['new-order', 'Đặt đơn', 'plus'],
        ['orders', 'Đơn hàng', 'list'],
        ['services', 'Bảng giá dịch vụ', 'grid'],
        ['add-funds', 'Nạp tiền', 'wallet'],
        ['transactions', 'Lịch sử giao dịch', 'receipt'],
        ['tickets', 'Hỗ trợ', 'lifebuoy'],
        ['updates', 'Cập nhật dịch vụ', 'bell'],
        ['api-docs', 'API', 'code'],
        ['account', 'Tài khoản', 'user'],
    ];
@endphp
<x-layouts.base :title="$title ?? null">
    <div x-data="{ sidebar: false }" class="min-h-screen lg:pl-64">
        {{-- Sidebar --}}
        <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform">
            <a href="{{ route('dashboard') }}" class="flex h-16 items-center gap-2 border-b border-slate-200 px-6 text-lg font-bold text-indigo-600">
                {{ config('app.name') }}
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
                    <x-svg-icon name="logout" />Đăng xuất
                </button>
            </form>
        </aside>

        {{-- Header --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-8">
            <button class="lg:hidden" @click="sidebar = true" aria-label="Mở menu"><x-svg-icon name="menu" class="size-6" /></button>
            <h1 class="flex-1 truncate text-lg font-semibold">{{ $title ?? '' }}</h1>
            <a href="{{ route('add-funds') }}" class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200">
                <x-svg-icon name="wallet" class="size-4 text-slate-500" />
                <span class="font-semibold" x-data x-text="$store.me.data?.balance ?? @js($user->displayCurrency->format($user->balance, roundUp: false))"></span>
            </a>
            <span class="hidden rounded-full px-2.5 py-1 text-xs font-semibold text-white sm:inline"
                  style="background-color: {{ $user->level->color ?: '#6366f1' }}">{{ $user->level->name }}</span>
            <span class="hidden text-sm text-slate-600 md:inline">{{ $user->username }}</span>
        </header>

        <main class="mx-auto max-w-7xl p-4 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
