<?php

namespace App\Modules\Markets;

use Illuminate\Support\Facades\DB;

class MarketState
{
    public const PUBLIC_STATUSES = ['open', 'closed', 'pending_resolution', 'disputed', 'resolved', 'settling', 'settled', 'cancelled', 'void'];

    private const TRANSITIONS = [
        'pending' => ['pending', 'open', 'rejected'],
        'open' => ['closed', 'settling'],
        'closed' => ['pending_resolution', 'disputed', 'resolved', 'settling'],
        'pending_resolution' => ['disputed', 'resolved', 'settling'],
        'disputed' => ['pending_resolution', 'resolved', 'settling'],
        'resolved' => ['settling'],
        'settling' => ['settled', 'cancelled'],
    ];

    public static function transition(object $market, string $to, string $reason, array $changes = [], ?int $actor = null, ?array $evidence = null): void
    {
        abort_unless(in_array($to, self::TRANSITIONS[$market->status] ?? [], true), 422, 'Transition de marché interdite.');
        self::persist($market, $to, $reason, $changes, $actor, $evidence);
    }

    private static function persist(object $market, string $to, string $reason, array $changes, ?int $actor, ?array $evidence): void
    {
        if ($market->status !== $to || $changes !== []) {
            DB::table('markets')->where('id', $market->id)->update([...$changes, 'status' => $to, 'updated_at' => now()]);
        }
        DB::table('market_transitions')->insert([
            'market_id' => $market->id, 'actor_id' => $actor, 'from_status' => $market->status,
            'to_status' => $to, 'reason' => $reason,
            'evidence' => $evidence === null ? null : json_encode($evidence, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
        foreach ($changes as $key => $value) {
            $market->$key = $value;
        }
        $market->status = $to;
    }

    public static function annotate(object $market, string $reason, array $changes, int $actor): void
    {
        self::persist($market, $market->status, $reason, $changes, $actor, null);
    }

    public static function closeIfDue(object $market): void
    {
        if ($market->status === 'open' && now()->gte($market->closes_at)) {
            self::transition($market, 'closed', 'Échéance des prises de position atteinte.', ['closed_at' => now()]);
        }
    }
}
