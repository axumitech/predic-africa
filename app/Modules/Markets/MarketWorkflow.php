<?php

namespace App\Modules\Markets;

use App\Models\User;
use App\Modules\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MarketWorkflow
{
    public function submit(int $creatorId, array $input, ?string $pipelineSource = null, ?CarbonImmutable $sourceClosesAt = null): int
    {
        return DB::transaction(function () use ($creatorId, $input, $pipelineSource, $sourceClosesAt) {
            $user = User::whereKey($creatorId)->lockForUpdate()->firstOrFail();
            abort_unless(! $user->suspended && in_array($user->role, ['trader', 'admin', 'superadmin'], true), 403);
            if ($pipelineSource !== null) {
                Gate::forUser($user)->authorize('admin');
            }
            $data = MarketDraft::validated($input);

            $id = DB::table('markets')->insertGetId([
                ...$data, 'creator_id' => $creatorId, 'status' => 'pending', 'pipeline_source' => $pipelineSource,
                'source_closes_at' => $sourceClosesAt,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($pipelineSource !== null) {
                Notifier::requestMarketReview($id, $data['title']);
            }

            return $id;
        }, 3);
    }

    public function moderate(int $actorId, int $id, string $status, array $draft = [], ?string $revision = null): void
    {
        abort_unless(in_array($status, ['open', 'rejected', 'pending'], true), 422);
        DB::transaction(function () use ($actorId, $id, $status, $draft, $revision) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $actor = User::whereKey($actorId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('admin');
            if ($market->status === $status && $status !== 'pending') {
                return;
            }
            abort_unless($market->status === 'pending', 422, 'Ce marché a déjà été modéré.');
            $african = str_starts_with((string) $market->pipeline_source, 'africa:');
            if (($draft !== [] || $revision !== null || ($african && $status === 'open'))
                && ! hash_equals(MarketDraft::revision($market), $revision ?? '')) {
                throw ValidationException::withMessages(['operation' => 'Ce brouillon a changé. Actualisez la page avant de confirmer.']);
            }
            $changes = [];
            if ($status === 'pending' || ($status === 'open' && ($african || $draft !== []))) {
                $changes = MarketDraft::validated($draft);
                if ($african) {
                    $cutoff = $market->source_closes_at;
                    if (! $cutoff || $changes['closes_at']->gt($cutoff)) {
                        throw ValidationException::withMessages(['closes_at' => 'La clôture doit respecter l’échéance maximale du calendrier officiel.']);
                    }
                }
            } elseif ($status === 'open') {
                try {
                    MarketDraft::validated((array) $market);
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['operation' => 'Marché invalide ou échéance dépassée. Publication refusée.']);
                }
            }
            if ($market->event_ends_at !== null && CarbonImmutable::parse($changes['closes_at'] ?? $market->closes_at)->gt($market->event_ends_at)) {
                throw ValidationException::withMessages(['closes_at' => 'La clôture doit précéder ou coïncider avec la fin de la période d’observation.']);
            }
            MarketState::transition($market, $status, 'Modération du marché.', $changes, $actorId);
            if ($status !== 'pending') {
                Notifier::send($market->creator_id, $status === 'open' ? 'Votre marché a été publié.' : 'Votre marché a été rejeté.');
            }
        }, 3);
    }
}
