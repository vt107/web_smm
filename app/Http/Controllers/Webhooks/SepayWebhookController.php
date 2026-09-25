<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\Payments\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Webhook SePay (https://docs.sepay.vn): cấu hình URL /api/webhooks/sepay/{payment_method_id},
 * kiểu chứng thực "API Key" → header "Authorization: Apikey {webhook_key}".
 */
class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentMethod $paymentMethod, DepositService $deposits): JsonResponse
    {
        abort_unless($paymentMethod->gateway === PaymentGateway::Sepay, 404);

        $expected = (string) $paymentMethod->configValue('webhook_key');
        $given = trim(Str::after((string) $request->header('Authorization'), 'Apikey'));

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'id' => ['required'],
            'transferType' => ['required', 'string'],
            'transferAmount' => ['required', 'numeric'],
            'transactionDate' => ['required', 'date'],
            'content' => ['nullable', 'string'],
            'accountNumber' => ['nullable', 'string'],
        ]);

        $account = (string) $paymentMethod->configValue('account_number');

        // Chỉ nhận tiền vào đúng tài khoản của kênh này.
        if ($data['transferType'] !== 'in' || ($account !== '' && ($data['accountNumber'] ?? $account) !== $account)) {
            return response()->json(['success' => true]);
        }

        $transfer = $deposits->recordTransfer($paymentMethod, [
            'external_id' => (string) $data['id'],
            'amount' => (string) $data['transferAmount'],
            'content' => $data['content'] ?? null,
            'transferred_at' => Carbon::parse($data['transactionDate'], 'Asia/Ho_Chi_Minh')->utc(),
            'raw' => $request->all(),
        ]);

        if ($transfer->wasRecentlyCreated) {
            $deposits->autoMatch($transfer);
        }

        return response()->json(['success' => true]);
    }
}
