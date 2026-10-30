<?php

namespace App\Http\Controllers;

use App\Modules\Money\Money;
use App\Modules\Money\Wallets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['currency' => ['required', Rule::in(array_keys(Money::SCALES))]]);
        DB::transaction(function () use ($request, $data) {
            $id = $request->user()->id;
            DB::table('users')->where('id', $id)->lockForUpdate()->firstOrFail();
            if (DB::table('payment_wallets')->where('user_id', $id)->where('currency', $data['currency'])->exists()) {
                throw ValidationException::withMessages(['currency' => 'Vous possédez déjà un portefeuille dans cette devise.']);
            }
            $account = DB::table('accounts')->where('user_id', $id)->where('currency', $data['currency'])->lockForUpdate()->first();
            $balance = 0;
            if ($account) {
                // Historical accounts can predate their payment wallet. Reuse the
                // account without resetting its balance or losing its ledger entries.
                $balance = (int) $account->balance;
                $entries = DB::table('ledger_entries')->where('account_id', $account->id);
                $movements = DB::table('payment_movements')->where('user_id', $id)->where('currency', $data['currency']);
                if ($balance < 0 || (int) (clone $entries)->sum('amount') !== $balance
                    || (int) $movements->sum('amount') !== $balance
                    || (clone $entries)->where('currency', '!=', $data['currency'])->exists()) {
                    throw ValidationException::withMessages(['currency' => 'Un compte existe déjà dans cette devise et nécessite un rapprochement. Contactez le support.']);
                }
            } else {
                DB::table('accounts')->insert(['user_id' => $id, 'code' => Wallets::accountCode($id, $data['currency']), 'currency' => $data['currency'], 'balance' => 0]);
            }
            DB::table('payment_wallets')->insert(['user_id' => $id, 'currency' => $data['currency'], 'balance' => $balance]);
            DB::table('users')->where('id', $id)->update(['active_wallet_currency' => $data['currency']]);
        });
        return back()->with('success', 'Portefeuille créé et sélectionné.');
    }

    public function select(Request $request)
    {
        $data = $request->validate(['currency' => 'required|string|size:3']);
        DB::transaction(function () use ($request, $data) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('payment_wallets')->where('user_id', $request->user()->id)->where('currency', $data['currency'])->exists(), 404);
            DB::table('users')->where('id', $request->user()->id)->update(['active_wallet_currency' => $data['currency']]);
        });
        return back()->with('success', 'Portefeuille sélectionné.');
    }
}
