@php
    $endpoint = url('/api/v2');
    $actions = [
        ['services', 'Danh sách dịch vụ', [], '[{"service": 1, "name": "Instagram Followers", "type": "Default", "category": "Instagram", "rate": "0.90", "min": 10, "max": 10000, "refill": true, "cancel": true, "dripfeed": false}]'],
        ['add', 'Đặt đơn', [['service', 'ID dịch vụ'], ['link', 'Link'], ['quantity', 'Số lượng'], ['runs', 'Không bắt buộc – số lần chạy (drip-feed)'], ['interval', 'Không bắt buộc – phút giữa các lần chạy'], ['comments / usernames / hashtags / username / answer_number', 'Tham số riêng theo loại dịch vụ']], '{"order": 23501}'],
        ['status', 'Trạng thái đơn', [['order', 'ID đơn'], ['orders', 'Hoặc nhiều ID, phân tách dấu phẩy (tối đa 100)']], '{"charge": "0.27819", "start_count": "3572", "status": "Partial", "remains": "157", "currency": "USD"}'],
        ['refill', 'Yêu cầu refill', [['order', 'ID đơn'], ['orders', 'Hoặc nhiều ID']], '{"refill": "1"}'],
        ['refill_status', 'Trạng thái refill', [['refill', 'ID refill'], ['refills', 'Hoặc nhiều ID']], '{"status": "Completed"}'],
        ['cancel', 'Huỷ đơn', [['order', 'ID đơn'], ['orders', 'Hoặc nhiều ID']], '[{"order": 9, "cancel": 1}, {"order": 2, "cancel": {"error": "Incorrect order ID"}}]'],
        ['balance', 'Số dư', [], '{"balance": "100.84", "currency": "USD"}'],
    ];
@endphp
<x-layouts.app title="API">
    <div class="space-y-6">
        <div class="card space-y-2 text-sm">
            <p>API tương thích chuẩn PerfectPanel – dùng được với các panel SMM khác.</p>
            <dl class="grid gap-2 sm:grid-cols-[140px_1fr]">
                <dt class="text-slate-500">Endpoint</dt><dd class="font-mono">{{ $endpoint }}</dd>
                <dt class="text-slate-500">Phương thức</dt><dd class="font-mono">POST</dd>
                <dt class="text-slate-500">API key</dt><dd>Tạo ở trang <a class="text-indigo-600 hover:underline" href="{{ route('account') }}">Tài khoản</a></dd>
                <dt class="text-slate-500">Định dạng</dt><dd>JSON. Lỗi trả về <code class="rounded bg-slate-100 px-1">{"error": "..."}</code>. Tiền tính bằng USD.</dd>
            </dl>
        </div>

        @foreach ($actions as [$action, $title, $params, $response])
            <div class="card">
                <h2 class="card-title">{{ $title }} <code class="ml-2 rounded bg-indigo-50 px-2 py-0.5 text-sm text-indigo-700">action={{ $action }}</code></h2>
                <table class="tbl mb-4">
                    <thead><tr><th class="w-64">Tham số</th><th>Mô tả</th></tr></thead>
                    <tbody>
                        <tr><td class="font-mono">key</td><td>API key của bạn</td></tr>
                        <tr><td class="font-mono">action</td><td class="font-mono">{{ $action }}</td></tr>
                        @foreach ($params as [$name, $description])
                            <tr><td class="font-mono">{{ $name }}</td><td>{{ $description }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">{{ $response }}</pre>
            </div>
        @endforeach

        <div class="card">
            <h2 class="card-title">Ví dụ (cURL)</h2>
            <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100">curl -X POST {{ $endpoint }} \
  -d key=YOUR_API_KEY -d action=add \
  -d service=1 -d link=https://instagram.com/username -d quantity=1000</pre>
        </div>
    </div>
</x-layouts.app>
