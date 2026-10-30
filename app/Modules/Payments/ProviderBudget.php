<?php

namespace App\Modules\Payments;

use Illuminate\Support\Facades\DB;

class ProviderBudget
{
    private function bucket(string $method): string
    {
        return hash('sha256', config('payments.secret_key').'|'.($method === 'POST' ? 'create' : 'read'));
    }

    public function acquire(string $method): void
    {
        $bucket = $this->bucket($method);
        $wait = DB::transaction(function () use ($bucket, $method) {
            DB::table('payment_rate_limits')->insertOrIgnore(['bucket' => $bucket, 'next_available_ms' => 0]);
            $row = DB::table('payment_rate_limits')->where('bucket', $bucket)->lockForUpdate()->firstOrFail();
            $now = (int) floor(microtime(true) * 1000);
            if ($row->next_available_ms > $now) {
                return max(1, (int) ceil(($row->next_available_ms - $now) / 1000));
            }
            // SQL-shared pacing, conservative for a rolling minute, across all workers.
            DB::table('payment_rate_limits')->where('bucket', $bucket)->update(['next_available_ms' => $now + ($method === 'POST' ? 1010 : 510)]);

            return 0;
        }, 3);
        if ($wait) {
            throw new GatewayFailure('local_rate_limit', false, $wait);
        }
    }

    public function defer(string $method, int $seconds): void
    {
        $bucket = $this->bucket($method);
        DB::transaction(function () use ($bucket, $seconds) {
            DB::table('payment_rate_limits')->where('bucket', $bucket)->lockForUpdate()->firstOrFail();
            DB::table('payment_rate_limits')->where('bucket', $bucket)->update(['next_available_ms' => (int) floor(microtime(true) * 1000) + $seconds * 1000]);
        }, 3);
    }
}
