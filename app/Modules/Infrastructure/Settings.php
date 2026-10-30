<?php

namespace App\Modules\Infrastructure;

use Illuminate\Support\Facades\DB;

class Settings
{
    // Read SQL at the point of use, including in long-lived queue workers.
    public function current(): array
    {
        $row = DB::table('infrastructure_settings')->where('id', 1)->firstOrFail();
        return [...['app_maintenance' => false, 'deposits_enabled' => true,
            'maintenance_message' => 'Les dépôts sont temporairement indisponibles.',
            'fx_refresh_enabled' => true, 'min_amount' => (string) config('payments.min_amount'),
            'max_amount' => (string) config('payments.max_amount'),
            'operator_codes' => array_values(array_column(config('payments.capabilities'), 'correspondent'))],
            ...json_decode($row->settings, true, 32, JSON_THROW_ON_ERROR), 'revision' => $row->revision];
    }

    public function capabilities(): array
    {
        $baseline = collect(config('payments.capabilities'))->keyBy('correspondent');
        $result = [];
        foreach ($this->current()['operator_codes'] as $code) {
            if ($baseline->has($code)) { $result[] = $baseline->get($code); continue; }
            $operator = config('ndsgpay_operators.'.$code);
            if (! is_array($operator)) continue;
            $phone = config('regions.phones.'.$operator['country']);
            $result[] = $baseline->get($code) ?? ['country' => $operator['country'], 'currency' => $operator['currency'],
                'correspondent' => $code, 'calling_code' => $phone['calling_code'], 'national_lengths' => [$phone['length']]];
        }
        return $result;
    }

    public function acceptingDeposits(): bool
    {
        $settings = $this->current();
        return $settings['deposits_enabled'] && ! $settings['app_maintenance'];
    }

    public static function heartbeat(string $component): void
    {
        DB::table('infrastructure_heartbeats')->upsert([['component' => $component, 'seen_at' => now()]], ['component'], ['seen_at']);
    }
}
