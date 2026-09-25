<x-layouts.guest title="Sign up">
    <h2 class="mb-6 text-xl font-semibold">Create your account</h2>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="ref" value="{{ old('ref', $ref) }}">
        @foreach ([
            ['name', 'Full name', 'text', 'name'],
            ['username', 'Username', 'text', 'username'],
            ['email', 'Email', 'email', 'email'],
            ['password', 'Password (min. 8 characters)', 'password', 'new-password'],
            ['password_confirmation', 'Confirm password', 'password', 'new-password'],
        ] as [$field, $label, $type, $autocomplete])
            <div>
                <label class="label" for="{{ $field }}">{{ $label }}</label>
                <input class="input" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" required
                       autocomplete="{{ $autocomplete }}" @unless($type === 'password') value="{{ old($field) }}" @endunless>
                @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <button class="btn-primary w-full">Create account</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Log in</a>
    </p>
</x-layouts.guest>
