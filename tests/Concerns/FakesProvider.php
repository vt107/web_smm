<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait FakesProvider
{
    /** Response giả của provider theo action. Test có thể sửa mảng này giữa chừng. */
    protected array $providerResponses = [];

    protected function fakeProvider(array $responses = []): void
    {
        $this->providerResponses = $responses + [
            'add' => ['order' => 9001],
            'balance' => ['balance' => '100.00', 'currency' => 'USD'],
        ];

        Http::fake([
            'provider.test/*' => function (Request $request) {
                $response = $this->providerResponses[$request->data()['action'] ?? ''] ?? ['error' => 'Unhandled action'];

                return Http::response(is_callable($response) ? $response($request->data()) : $response);
            },
        ]);
    }
}
