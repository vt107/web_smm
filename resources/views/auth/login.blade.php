<x-layouts.guest title="Log in">
    <h2 class="mb-6 text-xl font-semibold">Log in to your account</h2>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email or username</label>
            <input class="input" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me
        </label>
        <button class="btn-primary w-full">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        Don't have an account? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Sign up</a>
    </p>
</x-layouts.guest>
