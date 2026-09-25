@use('App\Support\Site')
@php
    $brand = [
        'instagram' => 'text-[#E4405F]', 'tiktok' => 'text-slate-900', 'youtube' => 'text-[#FF0000]',
        'facebook' => 'text-[#0866FF]', 'telegram' => 'text-[#26A5E4]', 'twitter' => 'text-slate-900',
        'spotify' => 'text-[#1DB954]', 'other' => 'text-indigo-600',
    ];
    $faq = Site::faq();
    $features = [
        ['Instant start', 'Most orders start within minutes. Start time and average completion time are shown for every service.', 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
        ['Automatic refunds', 'Canceled or partially delivered orders are refunded to your balance automatically – no tickets needed.', 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99'],
        ['Refill guarantee', 'Services with a refill guarantee can be refilled for free from your orders page if the count drops.', 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
        ['Reseller API', 'Connect your own panel with our PerfectPanel-compatible API and resell every service with your own prices.', 'M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5'],
        ['Loyalty discounts', 'The more you spend, the higher your level – and the bigger the discount on every order.', 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0'],
        ['24/7 support', 'Open a ticket any time from your dashboard – our team replies as fast as possible.', 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z'],
    ];
    $steps = [
        ['Create an account', 'Sign up for free with your email – it takes less than a minute.'],
        ['Add funds', 'Top up your balance by bank transfer or USDT. Funds are credited automatically.'],
        ['Place an order', 'Pick a service, paste your link and choose the quantity.'],
        ['Watch it grow', 'Track progress in real time. Undelivered parts are refunded automatically.'],
    ];
@endphp
<x-layouts.public :full-title="Site::seoTitle()">
    @push('head')
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                ['@type' => 'Organization', 'name' => Site::name(), 'url' => route('home'), 'logo' => Site::logoUrl()],
                ['@type' => 'WebSite', 'name' => Site::name(), 'url' => route('home'), 'description' => Site::get('description')],
                $faq ? ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($item) => [
                    '@type' => 'Question', 'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ], $faq)] : null,
            ])),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 bg-linear-to-b from-indigo-50 to-transparent"></div>
        <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-violet-500/10 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 lg:grid-cols-2 lg:px-8 lg:py-24">
            <div>
                <span class="badge-primary">Instant start · Automatic refunds · Reseller API</span>
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ Site::get('hero_title') }}</h1>
                <p class="mt-5 max-w-xl text-lg text-slate-600">{{ Site::get('hero_subtitle') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ auth()->check() ? route('new-order') : route('register') }}" class="btn-primary px-6 py-3 text-base">
                        {{ auth()->check() ? 'Place an order' : 'Get started – it\'s free' }}
                    </a>
                    <a href="{{ route('pricing') }}" class="btn-secondary px-6 py-3 text-base">View pricing</a>
                </div>
                <dl class="mt-10 grid max-w-lg grid-cols-3 gap-6">
                    <div><dt class="text-sm text-slate-500">Services</dt><dd class="text-2xl font-bold text-slate-900">{{ $serviceCount }}+</dd></div>
                    <div><dt class="text-sm text-slate-500">Platforms</dt><dd class="text-2xl font-bold text-slate-900">{{ $platformCount }}</dd></div>
                    @if ($minRate)
                        <div><dt class="text-sm text-slate-500">Starting from</dt><dd class="notranslate text-2xl font-bold text-slate-900" translate="no">${{ $minRate }}<span class="text-sm font-medium text-slate-500">/1K</span></dd></div>
                    @endif
                </dl>
            </div>

            {{-- Minh hoạ: thẻ đặt đơn --}}
            <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
                <div class="absolute -inset-4 rounded-3xl bg-linear-to-br from-indigo-500/20 to-violet-500/20 blur-2xl"></div>
                <div class="card relative space-y-4 p-6 shadow-xl">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold">New order</p>
                        <span class="badge-success">In progress</span>
                    </div>
                    <div class="flex gap-2">
                        @foreach (array_slice($platforms, 0, 5) as $p)
                            <span class="flex size-10 items-center justify-center rounded-lg bg-slate-100 {{ $brand[$p['platform']] ?? '' }}">
                                <x-platform-icon :platform="$p['platform']" class="size-5" />
                            </span>
                        @endforeach
                    </div>
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <p class="text-slate-500">Service</p>
                        <p class="font-medium">Instagram Followers [30 Days Refill]</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg border border-slate-200 p-3"><p class="text-slate-500">Quantity</p><p class="font-medium">5,000</p></div>
                        <div class="rounded-lg border border-slate-200 p-3"><p class="text-slate-500">Delivered</p><p class="font-medium">3,240</p></div>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 w-2/3 rounded-full bg-indigo-500"></div></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Platforms --}}
    <section id="services" class="scroll-mt-20 border-y border-slate-200 bg-surface py-16">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight">Services for every major platform</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">Followers, likes, views, comments, members and more – all in one place.</p>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($platforms as $p)
                    <a href="{{ route('pricing') }}#{{ $p['platform'] }}" class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 {{ $brand[$p['platform']] ?? '' }}">
                            <x-platform-icon :platform="$p['platform']" class="size-6" />
                        </span>
                        <span>
                            <span class="block font-semibold text-slate-900">{{ $p['label'] }}</span>
                            <span class="block text-sm text-slate-500">
                                {{ $p['count'] }} {{ Str::plural('service', $p['count']) }}@if ($p['from']) · from <span class="notranslate" translate="no">${{ $p['from'] }}</span>@endif
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="col-span-full text-center text-slate-500">Services are coming soon.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-16">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight">Why choose {{ Site::name() }}?</h2>
            <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as [$heading, $text, $icon])
                    <div class="card">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                        </span>
                        <h3 class="mt-4 font-semibold text-slate-900">{{ $heading }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="scroll-mt-20 border-y border-slate-200 bg-surface py-16">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <h2 class="text-center text-3xl font-bold tracking-tight">Get started in 4 simple steps</h2>
            <ol class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as $i => [$heading, $text])
                    <li class="relative rounded-xl border border-slate-200 p-5">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $i + 1 }}</span>
                        <h3 class="mt-4 font-semibold text-slate-900">{{ $heading }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Levels & payments --}}
    <section class="py-16">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 lg:grid-cols-2 lg:px-8">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">Spend more, save more</h2>
                <p class="mt-3 text-slate-600">Your level upgrades automatically as your total spending grows. Every level unlocks a permanent discount on all services.</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach ($levels as $level)
                        <div class="card flex items-center justify-between p-4">
                            <div>
                                <p class="font-semibold" style="color: {{ $level['color'] ?: '#6366f1' }}">{{ $level['name'] }}</p>
                                <p class="text-xs text-slate-500">Spend <span class="notranslate" translate="no">${{ $level['min_spent'] }}</span>+</p>
                            </div>
                            <p class="text-xl font-bold text-slate-900">{{ $level['discount'] > 0 ? '−'.$level['discount'].'%' : 'Base' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            <div>
                <h2 class="text-3xl font-bold tracking-tight">Easy payments</h2>
                <p class="mt-3 text-slate-600">Deposits are detected and credited to your balance automatically – no waiting for manual approval.</p>
                <ul class="mt-6 space-y-3">
                    @forelse ($paymentMethods as $method)
                        <li class="card flex items-center justify-between p-4">
                            <span class="font-medium">{{ $method['name'] }}</span>
                            @if ($method['bonus'] > 0)
                                <span class="badge-success">+{{ $method['bonus'] }}% bonus</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">Payment methods will be available soon.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    {{-- API --}}
    <section id="api" class="scroll-mt-20 border-y border-slate-200 bg-surface py-16">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 lg:grid-cols-2 lg:px-8">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">Built for resellers</h2>
                <p class="mt-3 text-slate-600">Our API follows the PerfectPanel standard used by most SMM panels, so you can connect in minutes and resell every service automatically.</p>
                <ul class="mt-6 space-y-2 text-sm text-slate-700">
                    <li>✓ Services, add order, status, refill, cancel and balance</li>
                    <li>✓ Up to 100 orders per status request</li>
                    <li>✓ Your level discount applies to API orders too</li>
                </ul>
            </div>
            <pre class="notranslate overflow-x-auto rounded-xl bg-gray-900 p-5 text-xs leading-relaxed text-gray-100" translate="no">curl -X POST {{ url('/api/v2') }} \
  -d key=YOUR_API_KEY \
  -d action=add \
  -d service=1 \
  -d link=https://instagram.com/username \
  -d quantity=1000

{"order": 23501}</pre>
        </div>
    </section>

    {{-- FAQ --}}
    @if ($faq)
        <section id="faq" class="scroll-mt-20 py-16">
            <div class="mx-auto max-w-3xl px-4 lg:px-8">
                <h2 class="text-center text-3xl font-bold tracking-tight">Frequently asked questions</h2>
                <div class="mt-10 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-surface">
                    @foreach ($faq as $item)
                        <details class="group p-5">
                            <summary class="flex items-center justify-between gap-4 font-medium text-slate-900 [&::-webkit-details-marker]:hidden">
                                {{ $item['question'] }}
                                <span class="text-slate-400 transition group-open:rotate-45">+</span>
                            </summary>
                            <p class="mt-3 text-sm whitespace-pre-line text-slate-600">{{ $item['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="px-4 pb-16">
        <div class="mx-auto max-w-5xl rounded-2xl bg-linear-to-br from-indigo-600 to-violet-600 px-6 py-12 text-center text-white">
            <h2 class="text-3xl font-bold tracking-tight">Ready to grow?</h2>
            <p class="mx-auto mt-3 max-w-xl text-white/80">Create a free account and place your first order in under two minutes.</p>
            <a href="{{ auth()->check() ? route('new-order') : route('register') }}" class="btn mt-8 bg-white px-6 py-3 text-base text-brand hover:bg-white/90">
                {{ auth()->check() ? 'Place an order' : 'Create free account' }}
            </a>
        </div>
    </section>
</x-layouts.public>
