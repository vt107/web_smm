<x-layouts.base :title="$title ?? null">
    <div class="absolute top-4 right-4"><x-preferences /></div>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-6 flex justify-center text-2xl text-slate-900"><x-logo size="h-10" /></a>
            <div class="card p-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.base>
