<x-layouts.base :title="$title ?? null">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <a href="/" class="mb-6 block text-center text-2xl font-bold text-indigo-600">{{ config('app.name') }}</a>
            <div class="card p-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.base>
