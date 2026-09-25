@use('App\Support\Site')
<x-layouts.public title="Pricing" description="Full price list of {{ Site::name() }}: followers, likes, views and more for Instagram, TikTok, YouTube, Facebook, Telegram and X.">
    <section class="mx-auto max-w-7xl px-4 py-12 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight">Pricing</h1>
        <p class="mt-2 text-slate-600">
            Prices per 1,000 in USD.
            @auth
                Your level discount is applied at checkout.
            @else
                <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Sign up</a> to order and unlock level discounts.
            @endauth
        </p>

        @if (count($platforms) > 1)
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="Platforms">
                @foreach ($platforms as $p)
                    <a href="{{ route('pricing', ['platform' => $p['value']]) }}" @class(['tab-active' => $p['value'] === $platform, 'tab' => $p['value'] !== $platform])>
                        <x-platform-icon :platform="$p['value']" class="mr-1 inline size-4 align-[-2px]" />{{ $p['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        @forelse ($categories as $category)
            <div id="category-{{ $category['id'] }}" class="card mt-6 scroll-mt-24 overflow-x-auto p-0">
                <h2 class="border-b border-slate-200 px-5 py-3 font-semibold">{{ $category['name'] }}</h2>
                <table class="tbl">
                    <thead><tr><th class="w-16">ID</th><th>Service</th><th>Rate / 1000</th><th>Min – Max</th><th>Refill</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($category['services'] as $service)
                            <tr>
                                <td>{{ $service['id'] }}</td>
                                <td>{{ $service['name'] }}</td>
                                <td class="notranslate font-medium whitespace-nowrap" translate="no">${{ \App\Support\Money::format($service['base_rate']) }}{{ $service['is_package'] ? ' / package' : '' }}</td>
                                <td class="whitespace-nowrap">{{ number_format($service['min']) }} – {{ number_format($service['max']) }}</td>
                                <td>{{ $service['refill'] ? ($service['refill_days'] ? $service['refill_days'].' days' : 'Yes') : '—' }}</td>
                                <td class="text-right">
                                    <a class="btn-primary btn-sm" href="{{ auth()->check() ? route('new-order', ['service' => $service['id']]) : route('register') }}">Order</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="card mt-6 text-center text-slate-500">Services are coming soon.</p>
        @endforelse
    </section>
</x-layouts.public>
