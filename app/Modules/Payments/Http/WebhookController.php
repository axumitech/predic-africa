<?php

namespace App\Modules\Payments\Http;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Ndsgpay;
use App\Modules\Payments\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignature $signature)
    {
        // Even infrastructure errors must be JSON, without session/UI rendering.
        $request->headers->set('Accept', 'application/json');
        $raw = $request->getContent();
        if (strlen($raw) > config('payments.webhook_max_bytes')) {
            return response()->json(['received' => false], 413);
        }
        if (! $signature->valid($raw, $request->header('X-NDSGPay-Signature', ''), $request->header('X-NDSGPay-Merchant', ''))) {
            return response()->json(['received' => false], 400);
        }
        try {
            $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return response()->json(['received' => false], 400);
        }
        if (! is_array($payload) || ! in_array($payload['event'] ?? null, ['deposit.updated', 'payout.updated'], true)
            || ! is_array($payload['data'] ?? null)) {
            return response()->json(['received' => false], 400);
        }
        $kind = $payload['event'] === 'deposit.updated' ? 'deposit' : 'withdraw';
        $id = $payload['data'][$kind === 'deposit' ? 'depositId' : 'payoutId'] ?? null;
        $status = $payload['data']['status'] ?? null;
        if (! Ndsgpay::validId($id) || ! in_array($status, ['COMPLETED', 'FAILED'], true)) {
            return response()->json(['received' => false], 400);
        }
        // Only retain reliable fields, never the full sensitive provider payload.
        DB::table('payment_inbox')->insertOrIgnore([
            'dedupe_key' => hash('sha256', 'ndsgpay|'.Ndsgpay::merchantScope().'|'.$kind.'|'.$id.'|'.$status),
            'merchant_id' => Ndsgpay::merchantScope() ?? '', 'kind' => $kind, 'provider_id' => $id,
            'provider_status' => $status, 'received_at' => now(), 'next_attempt_at' => now(),
        ]);

        // No dispatch dependency: scheduler discovers every undelivered inbox row.
        return response()->json(['received' => true]);
    }
}
