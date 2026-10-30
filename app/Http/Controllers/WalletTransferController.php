<?php

namespace App\Http\Controllers;

use App\Modules\Money\Money;
use App\Modules\Money\WalletTransfers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletTransferController extends Controller
{
    public function quote(Request $request, WalletTransfers $transfers)
    {
        $data = $request->validate(['source_currency' => ['required', Rule::in(array_keys(Money::SCALES))],
            'target_currency' => ['required', Rule::in(array_keys(Money::SCALES))], 'amount' => 'required']);
        return response()->json(WalletTransfers::publicData($transfers->quote($request->user()->id,
            $data['source_currency'], $data['target_currency'], $data['amount'])))->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Request $request, WalletTransfers $transfers)
    {
        $data = $request->validate(['quote_id' => 'required|uuid']);
        $transfers->confirm($request->user()->id, $data['quote_id']);
        return back()->with('success', 'Transfert entre portefeuilles effectué.');
    }
}
