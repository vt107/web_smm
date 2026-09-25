<x-layouts.guest title="Đăng nhập">
    <h2 class="mb-6 text-xl font-semibold">Đăng nhập</h2>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email hoặc username</label>
            <input class="input" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Mật khẩu</label>
            <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300"> Ghi nhớ đăng nhập
        </label>
        <button class="btn-primary w-full">Đăng nhập</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-600">
        Chưa có tài khoản? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Đăng ký</a>
    </p>
</x-layouts.guest>
