<?php

namespace App\Modules\Trading\Http;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingController extends Controller
{
    public function store(Request $request, int $id, Ledger $ledger)
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['yes', 'no'])], 'amount' => 'required|integer|min:100|max:1000000', 'reference' => 'required|uuid']);
        DB::transaction(function () use ($request, $id, $data, $ledger) {
            // Market first, then user, then accounts: same order as settlement.
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $prior = DB::table('ledger_transactions')->where('reference', $data['reference'])->first();
            if (! $prior && ($market->status !== 'open' || now()->gte($market->closes_at))) {
                throw ValidationException::withMessages(['amount' => 'Ce marché est fermé aux nouvelles positions.']);
            }
            $transaction = $ledger->transfer($request->user()->id, $data['reference'], 'Mise', (int) $data['amount'], false, "$id|{$data['outcome']}");
            if (DB::table('positions')->where('transaction_id', $transaction)->exists()) {
                return;
            }
            $odds = $data['outcome'] === 'yes' ? $market->yes_odds : $market->no_odds;
            DB::table('positions')->insert([
                'user_id' => $request->user()->id, 'market_id' => $id, 'transaction_id' => $transaction,
                'outcome' => $data['outcome'], 'stake' => $data['amount'], 'odds' => $odds,
                'payout' => intdiv((int) $data['amount'] * $odds, 100), 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        return redirect('/positions')->with('success', 'Position enregistrée.');
    }
}
