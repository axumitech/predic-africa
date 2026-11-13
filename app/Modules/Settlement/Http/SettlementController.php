<?php

namespace App\Modules\Settlement\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Markets\MarketState;
use App\Modules\Notifications\Notifier;
use App\Modules\Settlement\ResolveMarket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    public function store(Request $request, int $id, ResolveMarket $settlement)
    {
        Gate::authorize('admin');
        $data = $request->validate(['result' => ['required', Rule::in(['yes', 'no', 'void'])], 'source' => 'required|string|min:10|max:2000']);
        if (! app()->isProduction() && config('queue.default') === 'sync') {
            $settlement->resolve($id, $data['result'], $data['source'], $request->user()->id);

            return back()->with('success', 'Résolution enregistrée et soldes mis à jour.');
        }
        $settlement->record($id, $data['result'], $data['source'], $request->user()->id);

        return back()->with('success', 'Résolution enregistrée. Le règlement est programmé.');
    }

    public function review(Request $request, int $id)
    {
        Gate::authorize('admin');
        $data = $request->validate(['action' => 'required|in:dispute,retry,correction', 'reason' => 'required|string|min:10|max:2000']);
        $data['reason'] = trim($data['reason']);
        if (mb_strlen($data['reason']) < 10) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reason' => 'Veuillez justifier cette action.']);
        }
        DB::transaction(function () use ($request, $id, $data) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('admin');
            if ($data['action'] === 'correction') {
                abort_unless(in_array($market->status, ['settled', 'cancelled', 'void'], true)
                    || ($market->status === 'resolved' && $market->resolved_at === null),
                    422, 'Le règlement doit être terminé pour signaler une correction historique.');
                MarketState::annotate($market, 'Correction signalée : '.$data['reason'], [], $actor->id);

                return;
            }
            MarketState::closeIfDue($market);
            if ($data['action'] === 'dispute') {
                MarketState::transition($market, 'disputed', $data['reason'], ['processing_due_at' => null], $actor->id);
                Notifier::marketParticipants($id, "Le résultat du marché #$id est en contestation.");

                return;
            }

            $changes = ['processing_due_at' => now(), 'processing_error' => null, 'processing_failures' => 0];
            if ($market->status === 'disputed') {
                abort_unless($market->resolution_rule !== null, 422, 'Aucun contrat automatique configuré.');
                MarketState::transition($market, 'pending_resolution', $data['reason'], $changes, $actor->id);

                return;
            }
            abort_unless(($market->status === 'pending_resolution' && $market->resolution_rule !== null)
                || (in_array($market->status, ['resolved', 'settling'], true) && $market->resolved_at !== null),
                422, 'Aucun traitement à relancer.');
            MarketState::annotate($market, $data['reason'], $changes, $actor->id);
        }, 3);

        return back()->with('success', 'Action enregistrée.');
    }
}
