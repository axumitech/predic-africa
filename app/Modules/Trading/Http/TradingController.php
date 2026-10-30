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
        $data = $request->validate(['outcome' => ['required', Rule::in(['yes', 'no'])], 'amount' => ['required', function ($attribute, $value, $fail) {
            if (! is_string($value) && ! is_int($value)) $fail('Montant invalide.');
        }], 'reference' => 'required|uuid', 'quote_id' => 'nullable|uuid']);
        $positions->place($request->user()->id, $id, $data['outcome'], $data['amount'], $data['reference'], $data['quote_id'] ?? null);

        return redirect('/positions')->with('success', 'Position enregistrée.');
    }
}
