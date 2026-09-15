<?php

namespace App\Modules\Predictions\Http;

use App\Http\Controllers\Controller;
use App\Modules\Predictions\Infrastructure\PipelineClient;
use App\Modules\Predictions\Infrastructure\PipelineUnavailable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PipelineAdminController extends Controller
{
    public function generate(Request $request, PipelineClient $client)
    {
        abort_unless($request->user()->role === 'admin', 403);
        try {
            $client->generate();
        } catch (\Throwable $exception) {
            if (! $exception instanceof PipelineUnavailable) {
                report($exception);
            }
            throw ValidationException::withMessages(['pipeline' => 'Génération indisponible. Vérifiez le service Python ; aucun marché local n’a été publié.']);
        }

        return back()->with('success', 'Génération simulée terminée. Les suggestions restent à importer et à modérer.');
    }

    public function import(Request $request, PipelineClient $client)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['id' => 'required|integer|min:1']);
        try {
            $items = $client->read('markets', null)['data'];
        } catch (\Throwable $exception) {
            if (! $exception instanceof PipelineUnavailable) {
                report($exception);
            }
            throw ValidationException::withMessages(['pipeline' => 'Le pipeline est indisponible. Réessayez plus tard.']);
        }
        $item = collect($items)->first(fn ($item) => is_array($item) && ($item['id'] ?? null) == $data['id']);
        if (! $item || ($item['options'] ?? []) !== ['Oui', 'Non']) {
            throw ValidationException::withMessages(['pipeline' => 'Cette suggestion est absente ou ne propose pas les issues Oui / Non.']);
        }
        if (Validator::make($item, ['category' => 'required|string|max:30', 'odds' => 'required|array|size:2', 'odds.*' => 'required|numeric|between:1.01,10'])->fails()) {
            throw ValidationException::withMessages(['pipeline' => 'La catégorie ou les cotes de la suggestion sont invalides.']);
        }
        $category = ['crypto' => 'Crypto', 'politics' => 'Politique', 'cinema' => 'Société', 'economy' => 'Économie', 'music' => 'Musique', 'sports' => 'Sport', 'football' => 'Sport'];
        $input = [
            'title' => $item['title'] ?? null, 'description' => $item['description'] ?? null,
            'closes_at' => $item['expiry'] ?? null,
            'yes_odds' => is_numeric($item['odds'][0] ?? null) ? (int) round($item['odds'][0] * 100) : null,
            'no_odds' => is_numeric($item['odds'][1] ?? null) ? (int) round($item['odds'][1] * 100) : null,
        ];
        $validator = Validator::make($input, [
            'title' => 'required|string|min:10|max:200', 'description' => 'required|string|min:20|max:5000',
            'closes_at' => 'required|date|after:now', 'yes_odds' => 'required|integer|min:101|max:1000', 'no_odds' => 'required|integer|min:101|max:1000',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['pipeline' => 'Suggestion invalide ou expirée. Générez de nouvelles suggestions ou créez un marché manuellement.']);
        }
        $source = hash('sha256', config('predictions.url')).':'.$data['id'];
        $id = DB::transaction(function () use ($request, $input, $item, $category, $source) {
            DB::table('markets')->insertOrIgnore([
                ...$input, 'closes_at' => Carbon::parse($input['closes_at'])->utc(),
                'creator_id' => $request->user()->id, 'category' => $category[$item['category'] ?? ''] ?? 'Société',
                'pipeline_source' => $source, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('markets')->where('pipeline_source', $source)->value('id');
        });

        return redirect("/markets/$id")->with('success', 'Suggestion importée en attente de modération. Vérifiez ses règles et son échéance avant publication.');
    }
}
