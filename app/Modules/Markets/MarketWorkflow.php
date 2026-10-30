<?php

namespace App\Modules\Markets;

use App\Models\User;
use App\Modules\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MarketWorkflow
{
    public function submit(int $creatorId, array $input, ?string $pipelineSource = null): int
    {
        return DB::transaction(function () use ($creatorId, $input, $pipelineSource) {
            $user = User::whereKey($creatorId)->lockForUpdate()->firstOrFail();
            abort_unless(! $user->suspended && in_array($user->role, ['trader', 'admin', 'superadmin'], true), 403);
            if ($pipelineSource !== null) {
                Gate::forUser($user)->authorize('admin');
            }
            $data = MarketDraft::validated($input);

            return DB::table('markets')->insertGetId([
                ...$data, 'creator_id' => $creatorId, 'status' => 'pending', 'pipeline_source' => $pipelineSource,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    public function moderate(int $actorId, int $id, string $status): void
    {
        abort_unless(in_array($status, ['open', 'rejected'], true), 422);
        DB::transaction(function () use ($actorId, $id, $status) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $actor = User::whereKey($actorId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('admin');
            if ($market->status === $status) {
                return;
            }
            abort_unless($market->status === 'pending', 422, 'Ce marché a déjà été modéré.');
            if ($status === 'open') {
                try {
                    MarketDraft::validated((array) $market);
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['operation' => 'Marché invalide ou échéance dépassée. Publication refusée.']);
                }
            }
            DB::table('markets')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
            Notifier::send($market->creator_id, $status === 'open' ? 'Votre marché a été publié.' : 'Votre marché a été rejeté.');
        }, 3);
    }
}
