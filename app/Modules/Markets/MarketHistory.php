<?php

namespace App\Modules\Markets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MarketHistory
{
    public function forMarket(object $market): array
    {
        $close = CarbonImmutable::parse($market->closes_at, 'UTC');
        $end = $close->min(CarbonImmutable::now('UTC'));
        if ($market->result === 'void' && $market->updated_at) {
            $end = $end->min(CarbonImmutable::parse($market->resolved_at ?? $market->updated_at, 'UTC'));
        }
        $start = $market->created_at ? CarbonImmutable::parse($market->created_at, 'UTC')->min($end) : $end;
        $startAt = $start->timestamp;
        $endAt = $end->timestamp;
        $bucketSeconds = max(1, (int) ceil(($endAt - $startAt + 1) / 160));
        $bucket = match (DB::connection()->getDriverName()) {
            'pgsql' => 'FLOOR((EXTRACT(EPOCH FROM created_at) - ?) / ?)',
            'mysql', 'mariadb' => 'FLOOR((UNIX_TIMESTAMP(created_at) - ?) / ?)',
            default => "((CAST(strftime('%s', created_at) AS INTEGER) - ?) / ?)",
        };

        // Aggregate the original stakes in their common XAF reference value.
        $buckets = DB::table('positions')->where('market_id', $market->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('outcome', ['yes', 'no'])
            ->selectRaw("$bucket AS time_bucket, MAX(created_at) AS last_position_at", [$startAt, $bucketSeconds])
            ->selectRaw('SUM(CASE WHEN outcome = ? THEN stake ELSE 0 END) AS yes_stake, SUM(stake) AS total_stake', ['yes'])
            ->groupBy('time_bucket')->orderBy('time_bucket')->get();

        $yes = 0;
        $total = 0;
        $points = [];
        foreach ($buckets as $row) {
            $yes += $row->yes_stake;
            $total += $row->total_stake;
            if ($total > 0) {
                $points[] = [
                    'at' => CarbonImmutable::parse($row->last_position_at, 'UTC')->timestamp,
                    'yes' => round($yes / $total * 100, 2),
                ];
            }
        }

        return ['start' => $startAt, 'end' => $endAt, 'points' => $points];
    }
}
