<x-layouts.public :title="$title">
    <article class="mx-auto max-w-3xl px-4 py-12 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight">{{ $title }}</h1>
        <div class="legal mt-8">{!! $html !!}</div>
    </article>
</x-layouts.public>
