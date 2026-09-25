<x-layouts.base :title="$title ?? null">
    <div class="absolute top-4 right-4"><x-preferences /></div>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <a href="/" class="notranslate mb-6 block text-center text-2xl font-bold text-indigo-600" translate="no">{{ config('app.name') }}</a>
            <div class="card p-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.base>
