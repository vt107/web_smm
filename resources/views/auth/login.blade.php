@php($demoLogin = \App\Support\Demo\DemoMode::credentials('customer'))
<x-layouts.guest title="Log in">
    <h2 class="mb-6 text-xl font-semibold">Log in to your account</h2>
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email or username</label>
            <input class="input" id="email" name="email" value="{{ old('email', $demoLogin['email'] ?? '') }}" required autofocus autocomplete="username">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input" id="password" name="password" type="password" value="{{ $demoLogin['password'] ?? '' }}" required autocomplete="current-password">
        </div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 hover:underline">Forgot password?</a>
        </div>
        <button class="btn-primary w-full">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        Don't have an account? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Sign up</a>
    </p>
</x-layouts.guest>
