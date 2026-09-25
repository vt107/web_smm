<x-layouts.app title="Add funds">
    <div x-data="addFundsPage()" x-init="init()" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Tạo phiếu nạp --}}
            <div class="card" x-show="!deposit">
                <h2 class="card-title">Choose a payment method</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <template x-for="m in methods" :key="m.id">
                        <button type="button" @click="methodId = m.id"
                                :class="methodId === m.id ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:border-slate-300'"
                                class="rounded-lg border p-4 text-left">
                            <p class="font-medium" x-text="m.name"></p>
                            <p class="mt-1 text-xs text-slate-500">
                                Min. $<span x-text="m.min"></span>
                                <span x-show="m.bonus > 0" class="ml-2 font-semibold text-emerald-600" x-text="'+' + m.bonus + '% bonus'"></span>
                            </p>
                        </button>
                    </template>
                </div>
                <p x-show="loaded && methods.length === 0" class="text-sm text-slate-500">No payment methods are available right now.</p>

                <form x-show="method" class="mt-6 space-y-4" @submit.prevent="create()">
                    <div>
                        <label class="label">Amount to add (USD)</label>
                        <input class="input" type="number" step="0.01" :min="method?.min" :max="method?.max" x-model="amount" required>
                        <p class="hint" x-show="method && method.currency !== 'USD' && amount">
                            You'll pay about <b x-text="convert()"></b>
                            <span x-show="method?.bonus > 0"> · Bonus <b x-text="method?.bonus + '%'"></b></span>
                        </p>
                    </div>
                    <p class="text-sm whitespace-pre-line text-slate-600" x-show="method?.instructions" x-text="method?.instructions"></p>
                    <button class="btn-primary" :disabled="busy">Create deposit</button>
                </form>
            </div>

            {{-- Phiếu nạp đang chờ --}}
            <template x-if="deposit">
                <div class="card space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="card-title mb-0">Deposit <span x-text="deposit.code"></span></h2>
                        <span :class="'badge-' + deposit.status_color" x-text="deposit.status_label"></span>
                    </div>

                    <template x-if="deposit.status === 'completed'">
                        <div class="rounded-lg bg-emerald-50 p-4 text-emerald-800">
                            <b x-text="deposit.credited"></b> has been added to your balance. Thank you!
                        </div>
                    </template>

                    <template x-if="deposit.status === 'pending'">
                        <div class="grid gap-6 md:grid-cols-[200px_1fr]">
                            <div>
                                <img x-show="deposit.qr_url" :src="deposit.qr_url" alt="Payment QR code" class="w-full rounded-lg border border-slate-200">
                                <canvas x-show="!deposit.qr_url" x-ref="qr" class="rounded-lg border border-slate-200"></canvas>
                            </div>
                            <dl class="space-y-3 text-sm">
                                <template x-if="deposit.gateway === 'sepay'">
                                    <div class="space-y-3">
                                        <div><dt class="text-slate-500">Bank</dt><dd class="font-medium" x-text="deposit.bank_name"></dd></div>
                                        <div><dt class="text-slate-500">Account holder</dt><dd class="font-medium" x-text="deposit.account_name"></dd></div>
                                    </div>
                                </template>
                                <div>
                                    <dt class="text-slate-500" x-text="deposit.gateway === 'sepay' ? 'Account number' : 'Wallet address (' + (deposit.network || '').toUpperCase() + ')'"></dt>
                                    <dd class="flex items-center gap-2 font-mono font-medium break-all">
                                        <span x-text="deposit.pay_to"></span>
                                        <button type="button" @click="copyText(deposit.pay_to)" class="text-slate-400 hover:text-slate-700"><x-svg-icon name="copy" class="size-4" /></button>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Amount to send <span x-show="deposit.gateway === 'crypto'" class="text-red-600">(exact amount, including decimals)</span></dt>
                                    <dd class="flex items-center gap-2 text-lg font-bold">
                                        <span x-text="deposit.paid_amount + ' ' + deposit.paid_currency"></span>
                                        <button type="button" @click="copyText(deposit.paid_amount_raw)" class="text-slate-400 hover:text-slate-700"><x-svg-icon name="copy" class="size-4" /></button>
                                    </dd>
                                </div>
                                <div x-show="deposit.gateway === 'sepay'">
                                    <dt class="text-slate-500">Transfer description <span class="text-red-600">(required)</span></dt>
                                    <dd class="flex items-center gap-2 font-mono text-lg font-bold">
                                        <span x-text="deposit.code"></span>
                                        <button type="button" @click="copyText(deposit.code)" class="text-slate-400 hover:text-slate-700"><x-svg-icon name="copy" class="size-4" /></button>
                                    </dd>
                                </div>
                                <p class="text-slate-500">Expires in <b x-text="countdown"></b>. Your balance is credited automatically once the payment arrives.</p>
                            </dl>
                        </div>
                    </template>

                    <template x-if="deposit.status !== 'pending' && deposit.status !== 'completed'">
                        <p class="text-sm text-slate-600">This deposit has expired. If you already paid, paste the transaction ID in the claim box to receive your funds.</p>
                    </template>

                    <button class="btn-secondary" @click="closeDeposit()">New deposit</button>
                </div>
            </template>

            {{-- Lịch sử --}}
            <div class="card">
                <h2 class="card-title">Deposit history</h2>
                <div class="overflow-x-auto">
                    <table class="tbl">
                        <thead><tr><th>Code</th><th>Method</th><th>Amount</th><th>Credited</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <template x-for="d in history" :key="d.id">
                                <tr class="cursor-pointer hover:bg-slate-50" @click="d.status === 'pending' && open(d)">
                                    <td class="font-mono" x-text="d.code"></td>
                                    <td x-text="d.method"></td>
                                    <td class="whitespace-nowrap" x-text="d.paid_amount + ' ' + d.paid_currency"></td>
                                    <td x-text="d.credited ?? '—'"></td>
                                    <td><span :class="'badge-' + d.status_color" x-text="d.status_label"></span></td>
                                    <td class="whitespace-nowrap" x-text="d.created_at"></td>
                                </tr>
                            </template>
                            <tr x-show="loaded && history.length === 0"><td colspan="6" class="py-8 text-center text-slate-500">No deposits yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <x-pagination />
            </div>
        </div>

        {{-- Nhận giao dịch --}}
        <div class="card h-fit">
            <h2 class="card-title">Paid but not credited?</h2>
            <p class="mb-4 text-sm text-slate-600">
                For example, you forgot the transfer description or the exchange deducted a withdrawal fee. Paste the bank transaction ID or tx hash to claim your funds.
            </p>
            <form class="space-y-3" @submit.prevent="claim()">
                <select class="input" x-model.number="claimMethodId" required>
                    <template x-for="m in methods" :key="m.id"><option :value="m.id" x-text="m.name"></option></template>
                </select>
                <input class="input font-mono" x-model="txid" placeholder="Transaction ID / tx hash" required>
                <button class="btn-primary w-full" :disabled="busy">Claim funds</button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function addFundsPage() {
                return {
                    methods: [], methodId: null, amount: '', busy: false, loaded: false,
                    deposit: null, countdown: '', timer: null, poller: null,
                    history: [], meta: null,
                    claimMethodId: null, txid: '',

                    async init() {
                        this.methods = await api.get('/ajax/payment-methods');
                        this.methodId = this.methods[0]?.id ?? null;
                        this.claimMethodId = this.methodId;
                        await this.load();
                        this.loaded = true;
                        const pending = this.history.find(d => d.status === 'pending');
                        if (pending) this.open(pending);
                    },
                    get method() {
                        return this.methods.find(m => m.id === this.methodId) ?? null;
                    },
                    convert() {
                        const value = Number(this.amount) * this.method.exchange_rate;
                        return value.toLocaleString(this.method.currency === 'VND' ? 'vi-VN' : 'en-US', { maximumFractionDigits: 3 }) + ' ' + this.method.currency;
                    },
                    async load(page = 1) {
                        const res = await api.get('/ajax/deposits', { page });
                        this.history = res.data;
                        this.meta = res.meta;
                    },
                    async create() {
                        this.busy = true;
                        try {
                            this.open(await api.post('/ajax/deposits', { payment_method_id: this.methodId, amount: this.amount }));
                            this.load();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.busy = false;
                        }
                    },
                    open(deposit) {
                        this.deposit = deposit;
                        this.$nextTick(() => {
                            if (!deposit.qr_url && this.$refs.qr) drawQr(this.$refs.qr, deposit.pay_to);
                        });
                        this.startTimers();
                    },
                    closeDeposit() {
                        clearInterval(this.timer);
                        clearInterval(this.poller);
                        this.deposit = null;
                    },
                    startTimers() {
                        clearInterval(this.timer);
                        clearInterval(this.poller);
                        const tick = () => {
                            const left = Math.max(0, Math.floor((new Date(this.deposit.expires_at) - new Date()) / 1000));
                            this.countdown = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
                        };
                        tick();
                        this.timer = setInterval(tick, 1000);
                        // Kiểm tra trạng thái phiếu mỗi 5 giây cho tới khi xong.
                        this.poller = setInterval(async () => {
                            const fresh = (await api.get(`/ajax/deposits/${this.deposit.id}`)).data;
                            if (fresh.status !== this.deposit.status) {
                                this.deposit = fresh;
                                if (fresh.status === 'completed') {
                                    toast('Deposit completed');
                                    this.$store.me.refresh();
                                }
                                this.load();
                            }
                            if (fresh.status !== 'pending') clearInterval(this.poller);
                        }, 5000);
                    },
                    async claim() {
                        this.busy = true;
                        try {
                            const res = await api.post('/ajax/deposits/claim', { payment_method_id: this.claimMethodId, txid: this.txid });
                            toast(res.message);
                            this.txid = '';
                            this.$store.me.refresh();
                            this.load();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.busy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
