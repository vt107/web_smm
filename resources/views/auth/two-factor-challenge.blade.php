<x-layouts.guest title="Two-factor authentication">
    @php($initial = old('method', request('method', $hasApp ? 'app' : 'email')))
    <div x-data="{ method: @js($initial) }">
        <h2 class="mb-2 text-xl font-semibold">Two-factor authentication</h2>
        <p class="mb-6 text-sm text-slate-600">
            <span x-show="method === 'app'">Enter the 6-digit code from your authenticator app.</span>
            <span x-show="method === 'email'" x-cloak>Enter the 6-digit code we sent to <b>{{ $maskedEmail }}</b>.</span>
            <span x-show="method === 'recovery'" x-cloak>Enter one of your recovery codes. Each code can be used once.</span>
        </p>

        @if (session('status'))
            <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="method" :value="method">
            <div>
                <label class="label" for="code">Code</label>
                <input class="input text-center font-mono text-lg tracking-widest" id="code" name="code" required autofocus
                       autocomplete="one-time-code" :inputmode="method === 'recovery' ? 'text' : 'numeric'"
                       :placeholder="method === 'recovery' ? 'xxxxxxxxxx-xxxxxxxxxx' : '123456'">
                @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="btn-primary w-full">Verify</button>
        </form>

        <div class="mt-6 space-y-2 text-center text-sm">
            @if ($hasApp)
                <button type="button" x-show="method !== 'app'" x-cloak @click="method = 'app'" class="font-medium text-indigo-600 hover:underline">Use authenticator app</button>
                <button type="button" x-show="method !== 'recovery'" @click="method = 'recovery'" class="block w-full font-medium text-indigo-600 hover:underline">Use a recovery code</button>
            @endif
            @if ($hasEmail)
                <form method="POST" action="{{ route('two-factor.email') }}" x-show="method !== 'email'" @if ($hasApp) x-cloak @endif>
                    @csrf
                    <button class="font-medium text-indigo-600 hover:underline">Send a code to my email</button>
                </form>
                <form method="POST" action="{{ route('two-factor.email') }}" x-show="method === 'email'" @unless ($initial === 'email') x-cloak @endunless>
                    @csrf
                    <button class="font-medium text-slate-500 hover:underline">Resend code</button>
                </form>
            @endif
            <a href="{{ route('login') }}" class="block text-slate-500 hover:underline">← Back to log in</a>
        </div>
    </div>
</x-layouts.guest>
