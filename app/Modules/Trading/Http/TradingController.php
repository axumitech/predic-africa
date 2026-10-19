<?php

namespace App\Modules\Trading\Http;

use App\Http\Controllers\Controller;
use App\Modules\Trading\PlacePosition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TradingController extends Controller
{
    public function store(Request $request, int $id, PlacePosition $positions)
    {
        $data = $request->validate(['outcome' => ['required', Rule::in(['yes', 'no'])], 'amount' => 'required|integer|min:100|max:1000000', 'reference' => 'required|uuid']);
        $positions->place($request->user()->id, $id, $data['outcome'], (int) $data['amount'], $data['reference']);

        return redirect('/positions')->with('success', 'Position enregistrée.');
    }
}
