@php($user = auth()->user())
<x-layouts.app title="Account">
    <div x-data="accountPage(@js(['name' => $user->name, 'currency' => $user->currency, 'timezone' => $user->timezone]))" class="grid gap-6 lg:grid-cols-2">
        <form class="card space-y-4" @submit.prevent="save()">
            <h2 class="card-title">Profile</h2>
            <div>
                <label class="label">Username</label>
                <input class="input" value="{{ $user->username }}" disabled>
            </div>
            <div>
                <label class="label">Email</label>
                <input class="input" value="{{ $user->email }}" disabled>
            </div>
            <div>
                <label class="label">Full name</label>
                <input class="input" x-model="profile.name" required>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Display currency</label>
                    <select class="input" x-model="profile.currency">
                        @foreach (\App\Models\Currency::query()->where('status', 'active')->get() as $currency)
                            <option value="{{ $currency->code }}">{{ $currency->code }} – {{ $currency->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Balances and payments are always in USD.</p>
                </div>
                <div>
                    <label class="label">Timezone</label>
                    <select class="input" x-model="profile.timezone">
                        @foreach (['Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'Europe/London', 'America/New_York', 'UTC'] as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="btn-primary" :disabled="busy">Save</button>
        </form>

        <div class="space-y-6">
            <form class="card space-y-4" @submit.prevent="changePassword()">
                <h2 class="card-title">Change password</h2>
                <input class="input" type="password" placeholder="Current password" x-model="password.current_password" required autocomplete="current-password">
                <input class="input" type="password" placeholder="New password (min. 8 characters)" x-model="password.password" required autocomplete="new-password">
                <input class="input" type="password" placeholder="Confirm new password" x-model="password.password_confirmation" required autocomplete="new-password">
                <button class="btn-primary" :disabled="busy">Change password</button>
            </form>

            <div class="card space-y-3">
                <h2 class="card-title">API key</h2>
                <p class="text-sm text-slate-600">
                    @if ($user->api_key_last4)
                        Your current key ends with <b class="font-mono">…{{ $user->api_key_last4 }}</b>, created on {{ $user->api_key_created_at?->timezone($user->timezone)->format('d/m/Y H:i') }}.
                    @else
                        You haven't created an API key yet.
                    @endif
                    Generating a new key revokes the old one. See the <a href="{{ route('api-docs') }}" class="text-indigo-600 hover:underline">API documentation</a>.
                </p>
                <template x-if="apiKey">
                    <div class="flex items-center gap-2 rounded-lg bg-amber-50 p-3 font-mono text-sm break-all">
                        <span x-text="apiKey"></span>
                        <button type="button" @click="copyText(apiKey)" class="text-slate-500 hover:text-slate-800"><x-svg-icon name="copy" class="size-4" /></button>
                    </div>
                </template>
                <button class="btn-secondary" @click="generateKey()" :disabled="busy">Generate new API key</button>
            </div>
        </div>
    </div>

    {{-- Bảo mật 2 lớp --}}
    <div x-data="twoFactorSection()" x-init="load()" class="card mt-6 space-y-6">
        <div>
            <h2 class="card-title mb-1">Two-factor authentication</h2>
            <p class="text-sm text-slate-600">Add a second step when you log in. You can enable one or both methods.</p>
        </div>

        {{-- Authenticator app --}}
        <div class="rounded-lg border border-slate-200 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Authenticator app</p>
                    <p class="text-sm text-slate-500">Google Authenticator, Microsoft Authenticator, Authy…</p>
                </div>
                <span :class="status.app_enabled ? 'badge-success' : 'badge-gray'" x-text="status.app_enabled ? 'Enabled' : 'Off'"></span>
                <button x-show="!status.app_enabled && !setup" class="btn-primary btn-sm" @click="startApp()" :disabled="busy">Enable</button>
                <button x-show="status.app_enabled" class="btn-danger btn-sm" @click="askPassword('disableApp')" :disabled="busy">Disable</button>
            </div>

            <template x-if="setup">
                <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-[200px_1fr]">
                    <canvas x-ref="qr" class="rounded-lg border border-slate-200 bg-white"></canvas>
                    <form class="space-y-3" @submit.prevent="enableApp()">
                        <p class="text-sm text-slate-600">1. Scan the QR code with your authenticator app, or enter this key manually:</p>
                        <p class="notranslate flex items-center gap-2 font-mono text-sm break-all" translate="no">
                            <span x-text="setup.secret"></span>
                            <button type="button" @click="copyText(setup.secret)" class="text-slate-400 hover:text-slate-700"><x-svg-icon name="copy" class="size-4" /></button>
                        </p>
                        <label class="label" for="app-code">2. Enter the 6-digit code shown in the app</label>
                        <input id="app-code" class="input max-w-48 font-mono tracking-widest" x-model="code" inputmode="numeric" autocomplete="one-time-code" required>
                        <div class="flex gap-2">
                            <button class="btn-primary" :disabled="busy">Confirm</button>
                            <button type="button" class="btn-secondary" @click="setup = null">Cancel</button>
                        </div>
                    </form>
                </div>
            </template>

            <div x-show="status.app_enabled" class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                <span>Recovery codes left: <b x-text="status.recovery_codes_left"></b></span>
                <button class="btn-secondary btn-sm" @click="askPassword('regenerateCodes')" :disabled="busy">Generate new recovery codes</button>
            </div>

            <template x-if="recoveryCodes">
                <div class="mt-4 rounded-lg bg-amber-50 p-4 text-sm">
                    <p class="font-medium text-amber-800">Save these recovery codes somewhere safe. Each code works once if you lose your phone. They won't be shown again.</p>
                    <ul class="notranslate mt-3 grid gap-1 font-mono sm:grid-cols-2" translate="no">
                        <template x-for="c in recoveryCodes" :key="c"><li x-text="c"></li></template>
                    </ul>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="copyText(recoveryCodes.join('\n'))">Copy</button>
                        <button type="button" class="btn-secondary btn-sm" @click="downloadCodes()">Download</button>
                        <button type="button" class="btn-secondary btn-sm" @click="recoveryCodes = null">I saved them</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Email codes --}}
        <div class="rounded-lg border border-slate-200 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Email codes</p>
                    <p class="text-sm text-slate-500">Receive a 6-digit code at <span class="notranslate" translate="no" x-text="status.email"></span> when you log in.</p>
                </div>
                <span :class="status.email_enabled ? 'badge-success' : 'badge-gray'" x-text="status.email_enabled ? 'Enabled' : 'Off'"></span>
                <button x-show="!status.email_enabled && !emailSent" class="btn-primary btn-sm" @click="sendEmailCode()" :disabled="busy">Enable</button>
                <button x-show="status.email_enabled" class="btn-danger btn-sm" @click="askPassword('disableEmail')" :disabled="busy">Disable</button>
            </div>
            <form x-show="emailSent && !status.email_enabled" x-cloak class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4" @submit.prevent="enableEmail()">
                <div>
                    <label class="label" for="email-code">Enter the code we emailed you</label>
                    <input id="email-code" class="input max-w-48 font-mono tracking-widest" x-model="emailCode" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button class="btn-primary" :disabled="busy">Confirm</button>
                <button type="button" class="btn-secondary" @click="sendEmailCode()" :disabled="busy">Resend</button>
            </form>
        </div>

        {{-- Xác nhận mật khẩu cho thao tác tắt / tạo lại mã --}}
        <form x-show="pending" x-cloak class="flex flex-wrap items-end gap-2 rounded-lg bg-slate-50 p-4" @submit.prevent="confirmPassword()">
            <div>
                <label class="label" for="confirm-password">Confirm your password to continue</label>
                <input id="confirm-password" type="password" class="input" x-model="password" autocomplete="current-password" required>
            </div>
            <button class="btn-primary" :disabled="busy">Continue</button>
            <button type="button" class="btn-secondary" @click="pending = null; password = ''">Cancel</button>
        </form>
    </div>

    @push('scripts')
        <script>
            function twoFactorSection() {
                return {
                    status: {}, busy: false, setup: null, code: '', recoveryCodes: null,
                    emailSent: false, emailCode: '', pending: null, password: '',
                    async load() {
                        this.status = await api.get('/ajax/two-factor');
                    },
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
                    startApp() {
                        return this.run(async () => {
                            this.setup = await api.post('/ajax/two-factor/app/setup');
                            this.code = '';
                            this.$nextTick(() => drawQr(this.$refs.qr, this.setup.otpauth_url));
                        });
                    },
                    enableApp() {
                        return this.run(async () => {
                            const res = await api.post('/ajax/two-factor/app/enable', { code: this.code });
                            this.recoveryCodes = res.recovery_codes;
                            this.setup = null;
                            toast(res.message);
                            await this.load();
                        });
                    },
                    sendEmailCode() {
                        return this.run(async () => {
                            toast((await api.post('/ajax/two-factor/email/send')).message);
                            this.emailSent = true;
                        });
                    },
                    enableEmail() {
                        return this.run(async () => {
                            toast((await api.post('/ajax/two-factor/email/enable', { code: this.emailCode })).message);
                            this.emailSent = false;
                            this.emailCode = '';
                            await this.load();
                        });
                    },
                    askPassword(action) {
                        this.pending = action;
                        this.password = '';
                    },
                    confirmPassword() {
                        const urls = {
                            disableApp: '/ajax/two-factor/app/disable',
                            disableEmail: '/ajax/two-factor/email/disable',
                            regenerateCodes: '/ajax/two-factor/recovery-codes',
                        };
                        return this.run(async () => {
                            const res = await api.post(urls[this.pending], { password: this.password });
                            if (res.recovery_codes) this.recoveryCodes = res.recovery_codes;
                            toast(res.message);
                            this.pending = null;
                            this.password = '';
                            await this.load();
                        });
                    },
                    downloadCodes() {
                        const blob = new Blob([this.recoveryCodes.join('\n') + '\n'], { type: 'text/plain' });
                        const link = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'recovery-codes.txt' });
                        link.click();
                        URL.revokeObjectURL(link.href);
                    },
                };
            }

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
                        if (!confirm('Generating a new key will revoke the current one. Continue?')) return;
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
