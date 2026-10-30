<?php

namespace App\Modules\Payments;

use App\Modules\Money\Money;
use Illuminate\Support\Facades\DB;

class Outbox
{
    public function deliver(int $id): void
    {
        // Local notification and delivery marker share the same SQL transaction.
        // Future external deliveries need their own idempotent receiver/outbox consumer.
        DB::transaction(function () use ($id) {
            $event = DB::table('payment_outbox')->where('id', $id)->lockForUpdate()->first();
            if (! $event || $event->delivered_at) {
                return;
            }
            $payment = DB::table('payments')->find($event->payment_id);
            DB::table('platform_notifications')->insert([
                'user_id' => $payment->user_id,
                'message' => 'NDSGPay · '.$payment->reference.' · '.$event->event.' · '.Money::decimal((int) $payment->amount, $payment->currency).' '.$payment->currency,
                'created_at' => now(),
            ]);
            DB::table('payment_outbox')->where('id', $id)->update(['delivered_at' => now()]);
        }, 3);
    }
}
