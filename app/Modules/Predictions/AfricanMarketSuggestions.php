<?php

namespace App\Modules\Predictions;

use App\Modules\Markets\MarketWorkflow;
use App\Modules\Predictions\Infrastructure\OfficialAfricanCalendars;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AfricanMarketSuggestions
{
    private const LEGACY_SOURCES = [
        'caf-2027-3-rd-congo-sierra-leone' => 'can-2027-md3-cod-sle',
        'caf-2027-3-cote-divoire-gambie' => 'can-2027-md3-civ-gmb',
        'sarb-2026-11' => 'sarb-mpc-2026-11-rate-cut',
    ];

    public function __construct(private OfficialAfricanCalendars $calendars, private MarketWorkflow $markets) {}

    public function generate(int $actorId, ?string $category = null): array
    {
        ['events' => $events, 'unavailable' => $unavailable] = $this->calendars->discover($category);
        $created = 0;
        foreach ($events as $key => $event) {
            $source = 'africa:'.$key;
            $existing = DB::table('markets')->whereIn('pipeline_source', [
                $source, 'africa:'.(self::LEGACY_SOURCES[$key] ?? $key),
            ])->first();
            if ($existing) {
                // Respect an administrator's edits and tighten the deadline if the event moves forward.
                DB::table('markets')->where('id', $existing->id)->where('status', 'pending')
                    ->where('source_closes_at', '>', $event['closes_at'])
                    ->update(['source_closes_at' => $event['closes_at'], 'updated_at' => now()]);
                continue;
            }
            if ($event['closes_at']->lte(now())) {
                continue;
            }
            try {
                $this->markets->submit($actorId, [
                    'title' => $event['title'],
                    'description' => $event['rules']."\n\nAnnonce officielle\n".$event['announcement']."\n\nSource de résolution\n".$event['result'],
                    'category' => $event['category'],
                    'closes_at' => $event['closes_at'],
                    'yes_odds' => 200,
                    'no_odds' => 200,
                ], $source, $event['closes_at']);
                $created++;
            } catch (UniqueConstraintViolationException $exception) {
                if (! DB::table('markets')->where('pipeline_source', $source)->exists()) {
                    throw $exception;
                }
            }
        }

        return ['created' => $created, 'unavailable' => $unavailable];
    }
}
