<x-layouts.guest title="Đăng ký">
    <h2 class="mb-6 text-xl font-semibold">Tạo tài khoản</h2>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="ref" value="{{ old('ref', $ref) }}">
        @foreach ([
            ['name', 'Họ tên', 'text', 'name'],
            ['username', 'Username', 'text', 'username'],
            ['email', 'Email', 'email', 'email'],
            ['password', 'Mật khẩu (ít nhất 8 ký tự)', 'password', 'new-password'],
            ['password_confirmation', 'Nhập lại mật khẩu', 'password', 'new-password'],
        ] as [$field, $label, $type, $autocomplete])
            <div>
                <label class="label" for="{{ $field }}">{{ $label }}</label>
                <input class="input" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" required
                       autocomplete="{{ $autocomplete }}" @unless($type === 'password') value="{{ old($field) }}" @endunless>
                @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <button class="btn-primary w-full">Đăng ký</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        Đã có tài khoản? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Đăng nhập</a>
    </p>
</x-layouts.guest>
