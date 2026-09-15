<?php

namespace App\Modules\Settlement\Http;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Ledger;
use App\Modules\Notifications\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    public function store(Request $request, int $id, Ledger $ledger)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['result' => ['required', Rule::in(['yes', 'no', 'void'])], 'source' => 'required|string|min:10|max:2000']);
        DB::transaction(function () use ($id, $data, $ledger) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            if (in_array($market->status, ['resolved', 'void'])) {
                abort_unless($market->result === $data['result'], 422, 'Résultat déjà enregistré.');

                return;
            }
            abort_unless($market->status === 'open', 422, 'Seul un marché publié peut être résolu.');
            abort_if($data['result'] !== 'void' && now()->lt($market->closes_at), 422, 'Attendez la clôture du marché.');
            $positions = DB::table('positions')->where('market_id', $id)->where('status', 'open')->orderBy('user_id')->orderBy('id')->lockForUpdate()->get();
            foreach ($positions as $position) {
                DB::table('users')->where('id', $position->user_id)->lockForUpdate()->first();
                $refund = $data['result'] === 'void';
                $won = $position->outcome === $data['result'];
                if ($refund || $won) {
                    $ledger->transfer($position->user_id, (string) Str::uuid(), $refund ? 'Remboursement' : 'Gain', $refund ? $position->stake : $position->payout, true, "position:$position->id");
                }
                if (! $refund && ! $won) {
                    Notifier::send($position->user_id, "La position #$position->id est perdue après résolution du marché #$id.");
                }
                DB::table('positions')->where('id', $position->id)->update(['status' => $refund ? 'refunded' : ($won ? 'won' : 'lost'), 'updated_at' => now()]);
            }
            DB::table('markets')->where('id', $id)->update(['status' => $data['result'] === 'void' ? 'void' : 'resolved', 'result' => $data['result'], 'resolution_source' => $data['source'], 'updated_at' => now()]);
        }, 3);

        return back()->with('success', 'Résolution enregistrée et soldes mis à jour.');
    }
}
