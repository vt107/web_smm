@php
    $endpoint = url('/api/v2');
    $actions = [
        ['services', 'Service list', [], '[{"service": 1, "name": "Instagram Followers", "type": "Default", "category": "Instagram", "rate": "0.90", "min": 10, "max": 10000, "refill": true, "cancel": true, "dripfeed": false}]'],
        ['add', 'Add order', [['service', 'Service ID'], ['link', 'Link'], ['quantity', 'Quantity'], ['runs', 'Optional – number of runs (drip-feed)'], ['interval', 'Optional – minutes between runs'], ['comments / usernames / hashtags / username / answer_number', 'Extra parameters depending on service type']], '{"order": 23501}'],
        ['status', 'Order status', [['order', 'Order ID'], ['orders', 'Or multiple IDs, comma separated (max 100)']], '{"charge": "0.27819", "start_count": "3572", "status": "Partial", "remains": "157", "currency": "USD"}'],
        ['refill', 'Create refill', [['order', 'Order ID'], ['orders', 'Or multiple IDs']], '{"refill": "1"}'],
        ['refill_status', 'Refill status', [['refill', 'ID refill'], ['refills', 'Or multiple IDs']], '{"status": "Completed"}'],
        ['cancel', 'Cancel orders', [['order', 'Order ID'], ['orders', 'Or multiple IDs']], '[{"order": 9, "cancel": 1}, {"order": 2, "cancel": {"error": "Incorrect order ID"}}]'],
        ['balance', 'Balance', [], '{"balance": "100.84", "currency": "USD"}'],
    ];
@endphp
<x-layouts.app title="API">
    <div class="space-y-6">
        <div class="card space-y-2 text-sm">
            <p>Our API follows the PerfectPanel standard, so it works with other SMM panels out of the box.</p>
            <dl class="grid gap-2 sm:grid-cols-[140px_1fr]">
                <dt class="text-slate-500">Endpoint</dt><dd class="font-mono">{{ $endpoint }}</dd>
                <dt class="text-slate-500">Method</dt><dd class="font-mono">POST</dd>
                <dt class="text-slate-500">API key</dt><dd>Generate it on the <a class="text-indigo-600 hover:underline" href="{{ route('account') }}">Account</a> page</dd>
                <dt class="text-slate-500">Format</dt><dd>JSON. Errors are returned as <code class="rounded bg-slate-100 px-1">{"error": "..."}</code>. All amounts are in USD.</dd>
            </dl>
        </div>

        @foreach ($actions as [$action, $title, $params, $response])
            <div class="card">
                <h2 class="card-title">{{ $title }} <code class="ml-2 rounded bg-indigo-50 px-2 py-0.5 text-sm text-indigo-700">action={{ $action }}</code></h2>
                <table class="tbl mb-4">
                    <thead><tr><th class="w-64">Parameter</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td class="font-mono">key</td><td>Your API key</td></tr>
                        <tr><td class="font-mono">action</td><td class="font-mono">{{ $action }}</td></tr>
                        @foreach ($params as [$name, $description])
                            <tr><td class="font-mono">{{ $name }}</td><td>{{ $description }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">{{ $response }}</pre>
            </div>
        @endforeach

        <div class="card">
            <h2 class="card-title">Example (cURL)</h2>
            <pre class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">curl -X POST {{ $endpoint }} \
  -d key=YOUR_API_KEY -d action=add \
  -d service=1 -d link=https://instagram.com/username -d quantity=1000</pre>
        </div>
    </div>
</x-layouts.app>
