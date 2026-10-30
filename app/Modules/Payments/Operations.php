<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Money\Money;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Operations
{
    public function attach(string $reference, string $providerId, int $actorId, string $case): void
    {
        abort_unless(app(PaymentPolicy::class)->operate(User::findOrFail($actorId)), 403);
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{3,80}\z/', $case) && Ndsgpay::validId($providerId), 422);
        $snapshot = DB::table('payments')->where('reference', $reference)->firstOrFail();
        // Operator must first obtain identity evidence from NDSGPay support/dashboard.
        // Authenticated details must match even before association is committed.
        $deposit = $snapshot->kind === 'deposit';
        $result = app(Ndsgpay::class)->request('GET', ($deposit ? '/deposits/' : '/payouts/').$providerId);
        $details = $result[$deposit ? 'deposit' : 'payout'] ?? [];
        abort_unless(($details[$deposit ? 'depositId' : 'payoutId'] ?? null) === $providerId
            && Money::minor($details['amount'] ?? null, $snapshot->currency) === (int) $snapshot->amount && ($details['currency'] ?? '') === $snapshot->currency
            && ($details['correspondent'] ?? '') === $snapshot->correspondent
            && ($details['msisdn'] ?? '') === Crypt::decryptString($snapshot->phone_encrypted), 422, 'Association incohérente.');
        DB::transaction(function () use ($snapshot, $providerId, $actorId, $case) {
            DB::table('users')->where('id', $snapshot->user_id)->lockForUpdate()->firstOrFail();
            $payment = DB::table('payments')->where('id', $snapshot->id)->lockForUpdate()->firstOrFail();
            abort_unless((! $payment->provider_id && ! in_array($payment->local_state, Payments::FINAL))
                || ($payment->provider_id === $providerId && ! $payment->merchant_id), 409);
            DB::table('payments')->where('id', $payment->id)->update([
                'provider_id' => $providerId, 'merchant_id' => Ndsgpay::merchantScope(),
                'local_state' => in_array($payment->local_state, Payments::FINAL) ? $payment->local_state : 'awaiting_confirmation', 'next_check_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('payment_audit')->insert(['payment_id' => $payment->id, 'actor_id' => $actorId,
                'action' => 'attach_provider_id', 'case_reference' => $case, 'created_at' => now()]);
        }, 3);
        // Durable pending row is picked up even if dispatch/process dies here.
    }
}
