<?php

namespace App\Modules\Settlement\Http;

use App\Http\Controllers\Controller;
use App\Modules\Settlement\ResolveMarket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SettlementController extends Controller
{
    public function store(Request $request, int $id, ResolveMarket $settlement)
    {
        Gate::authorize('admin');
        $data = $request->validate(['result' => ['required', Rule::in(['yes', 'no', 'void'])], 'source' => 'required|string|min:10|max:2000']);
        $settlement->resolve($id, $data['result'], $data['source']);

        return back()->with('success', 'Résolution enregistrée et soldes mis à jour.');
    }
}
