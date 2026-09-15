<?php

namespace App\Modules\Predictions\Http;

use App\Http\Controllers\Controller;
use App\Modules\Predictions\Infrastructure\PipelineClient;
use App\Modules\Predictions\Infrastructure\PipelineUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PredictionController extends Controller
{
    public function predictions(Request $request, PipelineClient $pipeline): JsonResponse
    {
        return $this->read($request, $pipeline, 'predictions');
    }

    public function markets(Request $request, PipelineClient $pipeline): JsonResponse
    {
        return $this->read($request, $pipeline, 'markets');
    }

    private function read(Request $request, PipelineClient $pipeline, string $resource): JsonResponse
    {
        $validated = $request->validate(['domain' => ['nullable', 'string', 'max:50', 'regex:/^[a-z_-]+$/']]);

        if ($request->hasHeader('X-PredicAfrica-Pipeline')) {
            return response()->json(['status' => 'unavailable', 'data' => [], 'simulation' => true, 'message' => 'Cette URL est celle de Laravel, pas du service Python.'], 503);
        }

        try {
            return response()->json($pipeline->read($resource, $validated['domain'] ?? null));
        } catch (Throwable $exception) {
            if (! $exception instanceof PipelineUnavailable) {
                report($exception);
            }

            return response()->json([
                'status' => 'unavailable', 'data' => [], 'simulation' => true,
                'message' => 'Le pipeline de démonstration est indisponible. Le catalogue local reste accessible.',
            ], 503)->header('Retry-After', (string) ($exception instanceof PipelineUnavailable ? $exception->retryAfter : 30));
        }
    }
}
