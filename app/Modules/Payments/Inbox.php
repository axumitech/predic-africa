<?php

namespace App\Modules\Payments;

use Illuminate\Support\Facades\DB;

class Inbox
{
    public function process(int $id): void
    {
        $event = DB::table('payment_inbox')->find($id);
        if (! $event || $event->processed_at || now()->lt($event->next_attempt_at)) {
            return;
        }
        $payments = app(Payments::class);
        $payment = DB::table('payments')->where('provider_id', $event->provider_id)->where('kind', $event->kind)
            ->where('merchant_id', $event->merchant_id === '' ? null : $event->merchant_id)->first();
        if ($payment) {
            DB::table('payments')->where('id', $payment->id)->where('local_state', 'pending')->update(['local_state' => 'awaiting_confirmation']);
            if (in_array($payment->local_state, Payments::FINAL) && strtoupper($payment->local_state) !== $event->provider_status) {
                $payments->anomaly($payment->id, 'final_contradiction');
            }
            // Always authenticated verification before the first financial consequence.
            // Final duplicates can finish using the already verified final state.
            if (! ($payment->confirmed_at && strtoupper($payment->local_state) === $event->provider_status)) {
                $payments->refresh($payment, true);
            }
            $payment = DB::table('payments')->find($payment->id);
            if ($payment->confirmed_at && in_array($payment->local_state, Payments::FINAL)) {
                if (strtoupper($payment->local_state) !== $event->provider_status) {
                    $payments->anomaly($payment->id, 'webhook_get_contradiction');
                }
                DB::table('payment_inbox')->where('id', $id)->update(['processed_at' => now()]);

                return;
            }
        }
        $attempts = (int) $event->attempts + 1;
        DB::table('payment_inbox')->where('id', $id)->whereNull('processed_at')->update([
            'attempts' => $attempts, 'next_attempt_at' => now()->addSeconds(min(21600, 15 * (2 ** min(10, $attempts))) + random_int(0, 15)),
        ]);
        if (now()->diffInMinutes($event->received_at, true) >= config('payments.alert_after_minutes')) {
            $payments->anomaly($payment?->id, $payment ? 'unconfirmed_webhook' : 'unmatched_webhook', $event->dedupe_key);
        }
    }
}
