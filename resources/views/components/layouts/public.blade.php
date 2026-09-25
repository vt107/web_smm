@use('App\Support\Site')
@props(['title' => null, 'fullTitle' => null, 'description' => null])
@php
    $nav = [
        [route('home').'#services', 'Services'],
        [route('pricing'), 'Pricing'],
        [route('home').'#how-it-works', 'How it works'],
        [route('home').'#api', 'API'],
        [route('home').'#faq', 'FAQ'],
    ];
    $contacts = array_filter([
        'Email' => Site::get('support_email') ? 'mailto:'.Site::get('support_email') : null,
        'Telegram' => Site::get('telegram_url'),
        'WhatsApp' => Site::get('whatsapp_url'),
    ]);
@endphp
<x-layouts.base :title="$title" :full-title="$fullTitle" :description="$description">
    <div x-data="{ menu: false }">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-surface/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 lg:px-8">
                <a href="{{ route('home') }}" class="text-lg text-slate-900"><x-logo /></a>
                <nav class="hidden flex-1 items-center gap-6 text-sm font-medium text-slate-600 lg:flex" aria-label="Main">
                    @foreach ($nav as [$href, $label])
                        <a href="{{ $href }}" class="hover:text-slate-900">{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="ml-auto hidden md:block"><x-preferences /></div>
                <div class="hidden items-center gap-2 sm:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary">Log in</a>
                        <a href="{{ route('register') }}" class="btn-primary">Sign up</a>
                    @endauth
                </div>
                <button class="ml-auto rounded-lg p-1 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-gray-100" @click="menu = !menu" aria-label="Menu">
                    <x-svg-icon name="menu" class="size-6" />
                </button>
            </div>
            <div x-show="menu" x-cloak class="space-y-3 border-t border-slate-200 px-4 py-4 lg:hidden">
                @foreach ($nav as [$href, $label])
                    <a href="{{ $href }}" @click="menu = false" class="block text-sm font-medium text-slate-700">{{ $label }}</a>
                @endforeach
                <x-preferences />
                <div class="flex gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary flex-1">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary flex-1">Log in</a>
                        <a href="{{ route('register') }}" class="btn-primary flex-1">Sign up</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>{{ $slot }}</main>

        <footer class="border-t border-slate-200 bg-surface">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 md:grid-cols-4 lg:px-8">
                <div class="md:col-span-2">
                    <x-logo class="text-lg text-slate-900" />
                    <p class="mt-3 max-w-sm text-sm text-slate-500">{{ Site::get('tagline') }}</p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Explore</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600">
                        @foreach ($nav as [$href, $label])
                            <li><a href="{{ $href }}" class="hover:text-slate-900">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Account</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600">
                        <li><a href="{{ route('register') }}" class="hover:text-slate-900">Sign up</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-slate-900">Log in</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:text-slate-900">Terms of Service</a></li>
                        <li><a href="{{ route('privacy') }}" class="hover:text-slate-900">Privacy Policy</a></li>
                        @foreach ($contacts as $label => $href)
                            <li><a href="{{ $href }}" class="hover:text-slate-900" rel="noopener" target="_blank">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <p class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">{{ Site::footerText() }}</p>
        </footer>
    </div>
</x-layouts.base>
