<x-layouts.app title="Ticket #{{ $ticketId }}">
    <div x-data="ticketPage({{ $ticketId }})" x-init="load()" class="mx-auto max-w-3xl space-y-4">
        <template x-if="ticket">
            <div class="card flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold" x-text="ticket.subject"></h2>
                    <p class="text-sm text-slate-500">
                        <span x-text="ticket.category_label"></span>
                        <span x-show="ticket.order_ids" x-text="' · Orders: ' + ticket.order_ids"></span>
                    </p>
                </div>
                <span :class="'badge-' + ticket.status_color" x-text="ticket.status_label"></span>
                <button x-show="!ticket.is_closed" class="btn-secondary btn-sm" @click="close()">Close ticket</button>
            </div>
        </template>

        <template x-for="m in ticket?.messages ?? []" :key="m.id">
            <div class="card" :class="m.is_staff ? 'border-indigo-200 bg-indigo-50/40' : ''">
                <p class="mb-2 text-sm">
                    <b :class="m.is_staff ? 'text-indigo-700' : ''" x-text="m.author"></b>
                    <span class="text-slate-400" x-text="' · ' + m.created_at"></span>
                </p>
                <p class="text-sm whitespace-pre-line text-slate-700" x-text="m.message"></p>
                <div class="mt-3 flex flex-wrap gap-2" x-show="m.attachments.length">
                    <template x-for="url in m.attachments" :key="url">
                        <a :href="url" target="_blank"><img :src="url" class="h-24 rounded-lg border border-slate-200 object-cover"></a>
                    </template>
                </div>
            </div>
        </template>

        <form class="card space-y-3" @submit.prevent="reply()">
            <label class="label">Reply</label>
            <textarea class="input" rows="4" x-model="message" required></textarea>
            <div class="flex items-center justify-between">
                <input type="file" accept="image/*" multiple x-ref="files" class="text-sm">
                <button class="btn-primary" :disabled="busy">Send</button>
            </div>
            <p x-show="ticket?.is_closed" class="hint">This ticket is closed. Replying will reopen it.</p>
        </form>
    </div>

    @push('scripts')
        <script>
            function ticketPage(id) {
                return {
                    ticket: null, message: '', busy: false,
                    async load() {
                        this.ticket = (await api.get(`/ajax/tickets/${id}`)).data;
                    },
                    async reply() {
                        this.busy = true;
                        const data = new FormData();
                        data.append('message', this.message);
                        [...this.$refs.files.files].forEach(f => data.append('attachments[]', f));
                        try {
                            toast((await api('POST', `/ajax/tickets/${id}/reply`, data)).message);
                            this.message = '';
                            this.$refs.files.value = '';
                            this.load();
                        } catch (e) {
                            toast(e.message, 'error');
                        } finally {
                            this.busy = false;
                        }
                    },
                    async close() {
                        if (!confirm('Close this ticket?')) return;
                        toast((await api.post(`/ajax/tickets/${id}/close`)).message);
                        this.load();
                    },
                };
            }
        </script>
    @endpush
</x-layouts.app>
