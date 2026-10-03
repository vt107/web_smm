{{--
    Nút "Demo" nổi (chế độ demo chỉ xem). Được InjectDemoWidget chèn trước </body> của mọi trang HTML.
    Tự chứa CSS / JS (không phụ thuộc Tailwind của site hay Filament). Cấu hình ở config/demo.php.
--}}
@php
    $demoPortals = config('demo.portals', []);
    $demoUser = auth()->user();
    $demoFlash = request()->attributes->get('demo_blocked') ?? session('demo_blocked');
    // Chữ trên widget: mặc định tiếng Việt, ghi đè từng chuỗi bằng config('demo.texts') (site tiếng Anh...).
    $demoText = array_merge([
        'fab' => 'Demo',
        'fab_long' => ' · tài khoản',
        'panel_label' => 'Thông tin bản demo',
        'badge' => 'Bản demo · chỉ xem',
        'title' => 'Trải nghiệm :app',
        'intro' => 'Dữ liệu là mẫu. Bạn xem được mọi màn hình; thao tác thêm / sửa / xoá đã được tắt.',
        'close' => 'Đóng',
        'open' => 'Mở',
        'logged_in' => 'đang đăng nhập',
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'copy' => 'Sao chép',
        'copied' => 'Đã sao chép: ',
        'login_as' => 'Đăng nhập với tài khoản này',
        'footer' => 'Dữ liệu demo được làm mới tự động mỗi ngày.',
    ], (array) config('demo.texts', []));
@endphp
<div id="dmw" class="dmw" data-flash="{{ $demoFlash }}">
    <style>
        .dmw { --dmw-bg: #ffffff; --dmw-fg: #1c1b19; --dmw-muted: #6b675f; --dmw-line: #e7e4dd; --dmw-soft: #f5f3ee;
               --dmw-accent: #e2552b; --dmw-accent-fg: #ffffff; --dmw-ok: #1f8a4c; --dmw-shadow: 0 18px 50px -12px rgba(20,18,14,.35);
               font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: var(--dmw-fg); }
        @media (prefers-color-scheme: dark) {
            .dmw { --dmw-bg: #1d1c1a; --dmw-fg: #f1efe9; --dmw-muted: #a9a59d; --dmw-line: #34322e; --dmw-soft: #272623; --dmw-shadow: 0 18px 50px -12px rgba(0,0,0,.7); }
        }
        .dmw *, .dmw *::before, .dmw *::after { box-sizing: border-box; }
        .dmw button { font: inherit; color: inherit; cursor: pointer; }
        .dmw a { color: inherit; }
        .dmw-fab { position: fixed; left: 16px; bottom: 16px; z-index: 2147483000; display: inline-flex; align-items: center; gap: 8px;
                   padding: 10px 16px 10px 12px; border: 0; border-radius: 999px; background: var(--dmw-accent); color: var(--dmw-accent-fg) !important;
                   font-weight: 650; letter-spacing: .01em; box-shadow: var(--dmw-shadow); transition: transform .15s ease; }
        .dmw-fab:hover { transform: translateY(-2px); }
        .dmw-fab:focus-visible, .dmw-btn:focus-visible, .dmw-icon-btn:focus-visible, .dmw-x:focus-visible { outline: 2px solid var(--dmw-accent); outline-offset: 2px; }
        .dmw-fab svg { width: 20px; height: 20px; flex: none; }
        .dmw-dot { position: absolute; top: 2px; right: 2px; width: 10px; height: 10px; border-radius: 50%; background: #ffd23f; box-shadow: 0 0 0 2px var(--dmw-accent); }
        .dmw-dot::after { content: ""; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid #ffd23f; animation: dmw-ping 1.6s ease-out infinite; }
        @keyframes dmw-ping { from { transform: scale(.6); opacity: 1; } to { transform: scale(1.6); opacity: 0; } }
        .dmw.dmw-seen .dmw-dot { display: none; }
        .dmw-panel { position: fixed; left: 16px; bottom: 72px; z-index: 2147483001; width: min(400px, calc(100vw - 32px)); max-height: calc(100vh - 96px);
                     overflow: auto; background: var(--dmw-bg); border: 1px solid var(--dmw-line); border-radius: 16px; box-shadow: var(--dmw-shadow);
                     opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .16s ease, transform .16s ease; }
        .dmw.dmw-open .dmw-panel { opacity: 1; transform: none; pointer-events: auto; }
        .dmw-head { position: sticky; top: 0; display: flex; gap: 12px; align-items: flex-start; padding: 16px 16px 12px; background: var(--dmw-bg); border-bottom: 1px solid var(--dmw-line); }
        .dmw-head h2 { margin: 0; font-size: 16px; font-weight: 700; line-height: 1.3; color: var(--dmw-fg); }
        .dmw-head p { margin: 4px 0 0; color: var(--dmw-muted); font-size: 13px; }
        .dmw-x { margin-left: auto; flex: none; width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent; display: grid; place-items: center; color: var(--dmw-muted) !important; }
        .dmw-x:hover { background: var(--dmw-soft); }
        .dmw-badge { display: inline-block; margin-bottom: 6px; padding: 2px 8px; border-radius: 999px; background: var(--dmw-soft); color: var(--dmw-muted); font-size: 11px; font-weight: 650; text-transform: uppercase; letter-spacing: .06em; }
        .dmw-body { padding: 8px 16px 16px; }
        .dmw-portal { padding: 12px 0; border-bottom: 1px solid var(--dmw-line); }
        .dmw-portal:last-child { border-bottom: 0; }
        .dmw-portal-top { display: flex; align-items: baseline; gap: 8px; }
        .dmw-portal h3 { margin: 0; font-size: 14px; font-weight: 650; color: var(--dmw-fg); }
        .dmw-portal-desc { margin: 2px 0 0; color: var(--dmw-muted); font-size: 13px; }
        .dmw-open-link { margin-left: auto; flex: none; font-size: 13px; font-weight: 600; color: var(--dmw-accent) !important; text-decoration: none; white-space: nowrap; }
        .dmw-open-link:hover { text-decoration: underline; }
        .dmw-acc { margin-top: 8px; padding: 10px 12px; border-radius: 12px; background: var(--dmw-soft); }
        .dmw-acc-role { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .dmw-acc-role small { font-weight: 500; color: var(--dmw-ok); font-size: 12px; }
        .dmw-cred { display: flex; align-items: center; gap: 6px; font: 12.5px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--dmw-fg); }
        .dmw-cred span:first-child { width: 64px; flex: none; color: var(--dmw-muted); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 12px; }
        .dmw-cred code { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font: inherit; background: none; padding: 0; color: inherit; }
        .dmw-icon-btn { flex: none; width: 26px; height: 26px; border: 0; border-radius: 6px; background: transparent; display: grid; place-items: center; color: var(--dmw-muted) !important; }
        .dmw-icon-btn:hover { background: var(--dmw-line); color: var(--dmw-fg) !important; }
        .dmw-icon-btn svg { width: 15px; height: 15px; }
        .dmw-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; margin-top: 8px; width: 100%; padding: 8px 12px; border-radius: 10px;
                   background: var(--dmw-fg); color: var(--dmw-bg) !important; font-weight: 600; font-size: 13px; text-decoration: none !important; border: 0; }
        .dmw-btn:hover { opacity: .9; }
        .dmw-foot { padding: 12px 16px; border-top: 1px solid var(--dmw-line); color: var(--dmw-muted); font-size: 12.5px; }
        .dmw-toast { position: fixed; left: 50%; bottom: 24px; z-index: 2147483002; max-width: calc(100vw - 32px); transform: translate(-50%, 16px);
                     display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; background: #1c1b19; color: #f8f6f1;
                     box-shadow: var(--dmw-shadow); opacity: 0; pointer-events: none; transition: opacity .18s ease, transform .18s ease; font-size: 14px; }
        .dmw-toast.dmw-show { opacity: 1; transform: translate(-50%, 0); }
        .dmw-toast svg { width: 20px; height: 20px; flex: none; color: #ffb547; margin-top: 1px; }
        @media print { .dmw { display: none !important; } }
        @media (max-width: 480px) { .dmw-fab span.dmw-label-long { display: none; } }
    </style>

    <button type="button" class="dmw-fab" data-dmw-toggle aria-expanded="false" aria-controls="dmw-panel">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        <span>{{ $demoText['fab'] }}<span class="dmw-label-long">{{ $demoText['fab_long'] }}</span></span>
        <i class="dmw-dot" aria-hidden="true"></i>
    </button>

    <section id="dmw-panel" class="dmw-panel" role="dialog" aria-label="{{ $demoText['panel_label'] }}">
        <header class="dmw-head">
            <div>
                <span class="dmw-badge">{{ $demoText['badge'] }}</span>
                <h2>{{ str_replace(':app', config('app.name'), $demoText['title']) }}</h2>
                <p>{{ $demoText['intro'] }}</p>
            </div>
            <button type="button" class="dmw-x" data-dmw-close aria-label="{{ $demoText['close'] }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="dmw-body">
            @foreach ($demoPortals as $portal)
                <div class="dmw-portal">
                    <div class="dmw-portal-top">
                        <h3>{{ $portal['label'] }}</h3>
                        <a class="dmw-open-link" href="{{ url($portal['url']) }}">{{ $demoText['open'] }} &rarr;</a>
                    </div>
                    @if (! empty($portal['description']))
                        <p class="dmw-portal-desc">{{ $portal['description'] }}</p>
                    @endif

                    @foreach ($portal['accounts'] ?? [] as $account)
                        <div class="dmw-acc">
                            <div class="dmw-acc-role">
                                {{ $account['role'] }}
                                @if ($demoUser && $demoUser->email === $account['email'])
                                    <small>● {{ $demoText['logged_in'] }}</small>
                                @endif
                            </div>
                            @foreach ([$demoText['email'] => $account['email'], $demoText['password'] => $account['password']] as $credLabel => $credValue)
                                <div class="dmw-cred">
                                    <span>{{ $credLabel }}</span>
                                    <code>{{ $credValue }}</code>
                                    <button type="button" class="dmw-icon-btn" data-dmw-copy="{{ $credValue }}" aria-label="{{ $demoText['copy'] }} {{ mb_strtolower($credLabel) }}" title="{{ $demoText['copy'] }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                    </button>
                                </div>
                            @endforeach
                            <a class="dmw-btn" href="{{ route('demo.switch', $account['key']) }}">{{ $demoText['login_as'] }}</a>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <footer class="dmw-foot">{{ $demoText['footer'] }}</footer>
    </section>

    <div class="dmw-toast" role="status" aria-live="polite">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <span data-dmw-toast-text></span>
    </div>

    <script>
        (function () {
            var root = document.getElementById('dmw');
            if (!root) return;

            var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
                          set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
            if (store.get('dmw-seen')) root.classList.add('dmw-seen');

            var toggle = root.querySelector('[data-dmw-toggle]');
            function setOpen(open) {
                root.classList.toggle('dmw-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) { root.classList.add('dmw-seen'); store.set('dmw-seen', '1'); }
            }

            var toastTimer;
            function toast(message) {
                var el = root.querySelector('.dmw-toast');
                root.querySelector('[data-dmw-toast-text]').textContent = message || @json(config('demo.message'));
                el.classList.add('dmw-show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () { el.classList.remove('dmw-show'); }, 4500);
            }

            root.addEventListener('click', function (e) {
                if (e.target.closest('[data-dmw-toggle]')) { setOpen(!root.classList.contains('dmw-open')); return; }
                if (e.target.closest('[data-dmw-close]')) { setOpen(false); return; }
                var copy = e.target.closest('[data-dmw-copy]');
                if (copy) {
                    var text = copy.getAttribute('data-dmw-copy');
                    var done = function () { toast(@json($demoText['copied']) + text); };
                    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, done);
                    else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (err) {} t.remove(); done(); }
                }
            });

            // Các listener toàn cục chỉ gắn một lần (trang dùng wire:navigate thay body nhưng giữ window).
            if (!window.__dmwBound) {
                window.__dmwBound = true;
                document.addEventListener('click', function (e) {
                    var w = document.getElementById('dmw');
                    if (w && w.classList.contains('dmw-open') && !w.contains(e.target)) w.querySelector('[data-dmw-close]').click();
                });
                document.addEventListener('keydown', function (e) {
                    var w = document.getElementById('dmw');
                    if (e.key === 'Escape' && w && w.classList.contains('dmw-open')) w.querySelector('[data-dmw-close]').click();
                });
                window.addEventListener('demo-blocked', function (e) {
                    var w = document.getElementById('dmw');
                    if (w && w.__dmwToast) w.__dmwToast(e.detail && e.detail.message);
                });
            }
            root.__dmwToast = toast;

            if (root.dataset.flash) toast(root.dataset.flash);
        })();
    </script>
</div>
