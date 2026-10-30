<?php

namespace App\Modules\Money\Http;

use App\Http\Controllers\Controller;
use App\Modules\Money\PositionQuotes;
use App\Modules\Money\RateBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FxController extends Controller
{
    public function rates(Request $request, RateBook $rates)
    {
        $currency = DB::table('payment_wallets')->where('user_id', $request->user()->id)->value('currency') ?? 'XAF';
        return response()->json($rates->view($currency))->header('Cache-Control', 'private, no-store');
    }

    public function quote(Request $request, int $id, PositionQuotes $quotes)
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['yes', 'no'])],
            'amount' => 'required', 'reference' => 'required|uuid']);
        $quote = $quotes->create($request->user(), $id, $data['outcome'], $data['amount'], $data['reference']);
        return response()->json(PositionQuotes::publicData($quote))->header('Cache-Control', 'private, no-store');
    }
}
