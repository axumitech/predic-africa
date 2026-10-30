<?php

namespace App\Modules\Markets\Http;

use App\Http\Controllers\Controller;
use App\Modules\Markets\MarketWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MarketController extends Controller
{
    public function store(Request $request, MarketWorkflow $markets)
    {
        $id = $markets->submit($request->user()->id, $request->only(['title', 'description', 'category', 'closes_at', 'yes_odds', 'no_odds']));

        return redirect("/markets/$id")->with('success', 'Marché envoyé à la modération.');
    }

    public function moderate(Request $request, int $id, MarketWorkflow $markets)
    {
        Gate::authorize('admin');
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'rejected'])]]);
        $markets->moderate($request->user()->id, $id, $data['status']);

        return back()->with('success', 'Modération enregistrée.');
    }
}
