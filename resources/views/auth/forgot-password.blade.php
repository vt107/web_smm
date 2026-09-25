<x-layouts.guest title="Forgot password">
    <h2 class="mb-2 text-xl font-semibold">Forgot your password?</h2>
    <p class="mb-6 text-sm text-slate-600">Enter your email and we'll send you a link to choose a new password.</p>
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">← Back to log in</a>
    </p>
</x-layouts.guest>
