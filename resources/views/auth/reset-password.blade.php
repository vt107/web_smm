<x-layouts.guest title="Reset password">
    <h2 class="mb-6 text-xl font-semibold">Choose a new password</h2>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">New password (min. 8 characters)</label>
            <input class="input" id="password" name="password" type="password" required autofocus autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Confirm new password</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <button class="btn-primary w-full">Reset password</button>
    </form>
</x-layouts.guest>
