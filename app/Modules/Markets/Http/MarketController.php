<?php

namespace App\Modules\Markets\Http;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Notifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|min:10|max:200', 'description' => 'required|string|min:20|max:5000',
            'category' => ['required', Rule::in(['Sport', 'Politique', 'Musique', 'Économie', 'Crypto', 'Société'])],
            'closes_at' => 'required|date|after:now',
            'yes_odds' => 'required|integer|min:101|max:1000', 'no_odds' => 'required|integer|min:101|max:1000',
        ]);
        $data['closes_at'] = Carbon::parse($data['closes_at'])->utc();
        $id = DB::table('markets')->insertGetId([...$data, 'creator_id' => $request->user()->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return redirect("/markets/$id")->with('success', 'Marché envoyé à la modération.');
    }

    public function moderate(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'rejected'])]]);
        DB::transaction(function () use ($id, $data) {
            $market = DB::table('markets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($market, 404);
            abort_unless($market->status === 'pending', 422, 'Ce marché a déjà été modéré.');
            abort_if($data['status'] === 'open' && now()->gte($market->closes_at), 422, 'Échéance dépassée.');
            DB::table('markets')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            Notifier::send($market->creator_id, $data['status'] === 'open' ? 'Votre marché a été publié.' : 'Votre marché a été rejeté.');
        });

        return back()->with('success', 'Modération enregistrée.');
    }
}
