<?php

namespace App\Modules\Payments\Http;

use App\Http\Controllers\Controller;
use App\Modules\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Ledger $ledger)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['deposit', 'withdraw'])], 'amount' => 'required|integer|min:100|max:1000000', 'reference' => 'required|uuid']);
        DB::transaction(function () use ($request, $data, $ledger) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $ledger->transfer($request->user()->id, $data['reference'], $data['kind'] === 'deposit' ? 'Dépôt simulé' : 'Retrait simulé', (int) $data['amount'], $data['kind'] === 'deposit');
        }, 3);

        return back()->with('success', 'Opération simulée enregistrée. Aucun transfert Mobile Money réel.');
    }
}
