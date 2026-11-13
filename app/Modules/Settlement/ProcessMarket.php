<?php

namespace App\Modules\Settlement;

use App\Modules\Markets\MarketState;
use App\Modules\Notifications\Notifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMarket implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 180;

    public int $timeout = 60;

    public int $tries = 1;

    public const ACTIVE = ['open', 'closed', 'pending_resolution', 'resolved', 'settling'];

    public function __construct(public int $marketId)
    {
        $this->onQueue('markets');
    }

    public function uniqueId(): string
    {
        return (string) $this->marketId;
    }

    public function handle(OfficialResult $official, ResolveMarket $settlement): void
    {
        $market = DB::transaction(function () {
            $market = DB::table('markets')->where('id', $this->marketId)->lockForUpdate()->first();
            if (! $market || ! in_array($market->status, self::ACTIVE, true)
                || ($market->processing_error !== null && $market->processing_due_at === null)
                || ($market->processing_due_at !== null && now()->lt($market->processing_due_at))) {
                return null;
            }
            $wasOpen = $market->status === 'open';
            MarketState::closeIfDue($market);
            if ($wasOpen && $market->status === 'closed') {
                Notifier::marketParticipants($this->marketId, "Le marché #$this->marketId est fermé aux prises de position.");
            }
            if ($market->status === 'closed' && $market->event_ends_at !== null && now()->gte($market->event_ends_at)) {
                MarketState::transition($market, 'pending_resolution', 'Fin prévue de la période d’observation.', ['processing_due_at' => $market->resolution_rule === null ? null : now()->format('Y-m-d H:i:s')]);
            }

            return $market;
        }, 3);
        if (! $market) {
            return;
        }

        try {
            if ($market->status === 'pending_resolution' && $market->resolution_rule !== null && config('market_resolution.automatic_enabled')) {
                [$outcome, $evidence] = $official->check($market);
                DB::transaction(function () use ($outcome, $evidence, $settlement, $market) {
                    $locked = DB::table('markets')->where('id', $this->marketId)->lockForUpdate()->first();
                    if ($locked->status !== 'pending_resolution' || $locked->processing_due_at !== $market->processing_due_at) {
                        return;
                    }
                    if (in_array($outcome, ['disputed', 'review'], true)) {
                        $reason = $outcome === 'disputed' ? 'Résultats officiels contradictoires.' : 'Décision contractuelle soumise à vérification.';
                        MarketState::transition($locked, 'disputed', $reason, [
                            'processing_due_at' => null, 'processing_error' => null, 'processing_failures' => 0,
                        ], evidence: $evidence);
                    } elseif ($outcome === 'undetermined') {
                        DB::table('markets')->where('id', $this->marketId)->update([
                            'processing_due_at' => now()->addMinutes(15), 'processing_error' => null, 'processing_failures' => 0,
                        ]);
                    } else {
                        $settlement->record($this->marketId, $outcome, 'Sources officielles : '.$evidence[0]['url'], evidence: $evidence);
                    }
                    if ($locked->status === 'disputed') {
                        Notifier::marketParticipants($this->marketId, "Le résultat du marché #$this->marketId est en contestation.");
                    }
                }, 3);
            }
            if (in_array($market->status, ['resolved', 'settling'], true)) {
                $settlement->settleBatch($this->marketId);
            }
        } catch (Throwable $exception) {
            DB::transaction(function () use ($market, $exception) {
                $locked = DB::table('markets')->where('id', $this->marketId)->lockForUpdate()->first();
                if ($locked->status !== $market->status || $locked->processing_due_at !== $market->processing_due_at) {
                    return;
                }
                $attempt = $locked->processing_failures + 1;
                DB::table('markets')->where('id', $this->marketId)->update([
                    'processing_failures' => $attempt,
                    'processing_error' => $market->status === 'pending_resolution' ? 'verification_failed' : 'settlement_failed',
                    'processing_due_at' => $attempt >= 8 ? null : now()->addMinutes(2 ** $attempt),
                ]);
                Log::log($attempt >= 5 ? 'error' : 'warning', 'Échec du traitement d’un marché.', [
                    'market_id' => $this->marketId, 'status' => $locked->status, 'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);
            }, 3);
        }
    }
}
