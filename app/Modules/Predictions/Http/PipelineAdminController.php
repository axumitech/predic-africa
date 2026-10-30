<?php

namespace App\Modules\Predictions\Http;

use App\Http\Controllers\Controller;
use App\Modules\Predictions\ImportSuggestion;
use App\Modules\Predictions\Infrastructure\PipelineClient;
use App\Modules\Predictions\Infrastructure\PipelineUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PipelineAdminController extends Controller
{
    public function generate(Request $request, PipelineClient $client)
    {
        Gate::authorize('admin');
        try {
            $client->generate();
        } catch (\Throwable $exception) {
            if (! $exception instanceof PipelineUnavailable) {
                report($exception);
            }
            throw ValidationException::withMessages(['pipeline' => 'Génération indisponible. Vérifiez le service Python ; aucun marché local n’a été publié.']);
        }

        return back()->with('success', 'Génération terminée. Les suggestions restent à importer et à modérer.');
    }

    public function import(Request $request, ImportSuggestion $importer)
    {
        Gate::authorize('admin');
        $data = $request->validate(['id' => 'required|integer|min:1']);
        try {
            $id = $importer->import($request->user()->id, (int) $data['id']);
        } catch (PipelineUnavailable) {
            throw ValidationException::withMessages(['pipeline' => 'Le pipeline est indisponible. Réessayez plus tard.']);
        }

        return redirect("/markets/$id")->with('success', 'Suggestion disponible dans l’espace local. Vérifiez ses règles, son échéance et son état de modération.');
    }
}
