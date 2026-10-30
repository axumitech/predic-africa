<?php

namespace App\Modules\Payments;

use App\Modules\Payments\Jobs\ProcessPayment;
use Illuminate\Support\Facades\DB;

class Recovery
{
    public function dispatch(): int
    {
        DB::table('payments')->where('local_state', 'initiating')->where('initiated_at', '<', now()->subMinutes(2))
            ->update(['local_state' => 'initiation_unknown', 'status' => 'UNKNOWN', 'error_code' => 'creation_interrupted', 'updated_at' => now()]);
        DB::table('payments')->whereNotIn('local_state', Payments::FINAL)->where('initiated_at', '<', now()->subMinutes(config('payments.alert_after_minutes')))
            ->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $payment) {
                    app(Payments::class)->anomaly($payment->id, 'overdue_payment');
                }
            });
        $count = 0;
        foreach (['inbox' => 'payment_inbox', 'outbox' => 'payment_outbox', 'refresh' => 'payments'] as $work => $table) {
            $query = DB::table($table);
            if ($work === 'inbox') {
                $query->whereNull('processed_at')->where('next_attempt_at', '<=', now());
            } elseif ($work === 'outbox') {
                $query->whereNull('delivered_at');
            } else {
                $query->whereNotNull('provider_id')->where(fn ($q) => $q->whereNotIn('local_state', Payments::FINAL)->orWhereNull('confirmed_at'))
                    ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('check_lease_until')->orWhere('check_lease_until', '<=', now()));
            }
            foreach ($query->orderBy('id')->limit(100)->pluck('id') as $id) {
                ProcessPayment::dispatch($work, $id);
                $count++;
            }
        }

        return $count;
    }
}
