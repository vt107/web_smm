@php($user = auth()->user())
<x-layouts.app title="Tài khoản">
    <div x-data="accountPage(@js(['name' => $user->name, 'currency' => $user->currency, 'timezone' => $user->timezone]))" class="grid gap-6 lg:grid-cols-2">
        <form class="card space-y-4" @submit.prevent="save()">
            <h2 class="card-title">Thông tin</h2>
            <div>
                <label class="label">Username</label>
                <input class="input" value="{{ $user->username }}" disabled>
            </div>
            <div>
                <label class="label">Email</label>
                <input class="input" value="{{ $user->email }}" disabled>
            </div>
            <div>
                <label class="label">Họ tên</label>
                <input class="input" x-model="profile.name" required>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Tiền tệ hiển thị</label>
                    <select class="input" x-model="profile.currency">
                        @foreach (\App\Models\Currency::query()->where('status', 'active')->get() as $currency)
                            <option value="{{ $currency->code }}">{{ $currency->code }} – {{ $currency->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Số dư và thanh toán luôn tính bằng USD.</p>
                </div>
                <div>
                    <label class="label">Múi giờ</label>
                    <select class="input" x-model="profile.timezone">
                        @foreach (['Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'Europe/London', 'America/New_York', 'UTC'] as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="btn-primary" :disabled="busy">Lưu</button>
        </form>

        <div class="space-y-6">
            <form class="card space-y-4" @submit.prevent="changePassword()">
                <h2 class="card-title">Đổi mật khẩu</h2>
                <input class="input" type="password" placeholder="Mật khẩu hiện tại" x-model="password.current_password" required autocomplete="current-password">
                <input class="input" type="password" placeholder="Mật khẩu mới (ít nhất 8 ký tự)" x-model="password.password" required autocomplete="new-password">
                <input class="input" type="password" placeholder="Nhập lại mật khẩu mới" x-model="password.password_confirmation" required autocomplete="new-password">
                <button class="btn-primary" :disabled="busy">Đổi mật khẩu</button>
            </form>

            <div class="card space-y-3">
                <h2 class="card-title">API key</h2>
                <p class="text-sm text-slate-600">
                    @if ($user->api_key_last4)
                        Key hiện tại kết thúc bằng <b class="font-mono">…{{ $user->api_key_last4 }}</b>, tạo lúc {{ $user->api_key_created_at?->timezone($user->timezone)->format('d/m/Y H:i') }}.
                    @else
                        Bạn chưa tạo API key.
                    @endif
                    Tạo key mới sẽ vô hiệu hoá key cũ. Xem <a href="{{ route('api-docs') }}" class="text-indigo-600 hover:underline">tài liệu API</a>.
                </p>
                <template x-if="apiKey">
                    <div class="flex items-center gap-2 rounded-lg bg-amber-50 p-3 font-mono text-sm break-all">
                        <span x-text="apiKey"></span>
                        <button type="button" @click="copyText(apiKey)" class="text-slate-500 hover:text-slate-800"><x-svg-icon name="copy" class="size-4" /></button>
                    </div>
                </template>
                <button class="btn-secondary" @click="generateKey()" :disabled="busy">Tạo API key mới</button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function accountPage(profile) {
                return {
                    profile, busy: false, apiKey: null,
                    password: { current_password: '', password: '', password_confirmation: '' },
                    async run(fn) {
                        this.busy = true;
                        try {
                            await fn();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.busy = false;
                        }
                    },
                    save() {
                        return this.run(async () => {
                            toast((await api.put('/ajax/account', this.profile)).message);
                            this.$store.me.refresh();
                        });
                    },
                    changePassword() {
                        return this.run(async () => {
                            toast((await api.put('/ajax/account/password', this.password)).message);
                            this.password = { current_password: '', password: '', password_confirmation: '' };
                        });
                    },
                    generateKey() {
                        if (!confirm('Tạo key mới sẽ vô hiệu hoá key cũ. Tiếp tục?')) return;
                        return this.run(async () => {
                            const res = await api.post('/ajax/account/api-key');
                            this.apiKey = res.key;
                            toast(res.message);
                        });
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
