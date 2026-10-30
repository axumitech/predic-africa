<?php

namespace App\Modules\Predictions;

use App\Models\User;
use App\Modules\Markets\MarketWorkflow;
use App\Modules\Predictions\Infrastructure\PipelineClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ImportSuggestion
{
    public function __construct(private PipelineClient $client, private MarketWorkflow $markets) {}

    public function import(int $actorId, int $suggestionId): int
    {
        Gate::forUser(User::findOrFail($actorId))->authorize('admin');
        $sources = $this->client->sourceKeys($suggestionId);
        // Replay is a local lookup, even if the provider is down or the item
        // has since expired/disappeared. Never overwrite moderation or rules.
        if ($existing = $this->existing($sources)) {
            return $existing;
        }
        $items = $this->client->read('markets', null, fresh: true)['data'];
        $item = collect($items)->first(fn ($item) => $item['id'] === $suggestionId);
        $categories = ['crypto' => 'Crypto', 'politics' => 'Politique', 'cinema' => 'Société', 'economy' => 'Économie', 'music' => 'Musique', 'sports' => 'Sport', 'football' => 'Sport'];
        if (! $item || ($item['options'] ?? null) !== ['Oui', 'Non']
            || ! is_string($item['category'] ?? null) || ! isset($categories[$item['category']])
            || (isset($item['status']) && $item['status'] !== 'active')
            || ! is_array($item['odds'] ?? null) || ! array_is_list($item['odds']) || count($item['odds']) !== 2) {
            throw ValidationException::withMessages(['pipeline' => 'Suggestion absente, inactive ou incompatible avec un marché Oui / Non.']);
        }
        try {
            return $this->markets->submit($actorId, [
                'title' => $item['title'] ?? null, 'description' => $item['description'] ?? null,
                'category' => $categories[$item['category']], 'closes_at' => $item['expiry'] ?? null,
                'yes_odds' => $this->hundredths($item['odds'][0]), 'no_odds' => $this->hundredths($item['odds'][1]),
            ], $sources[0]);
        } catch (UniqueConstraintViolationException $exception) {
            // Catch only a proven source collision, not unrelated SQL failures.
            return $this->existing($sources) ?? throw $exception;
        } catch (ValidationException) {
            throw ValidationException::withMessages(['pipeline' => 'Suggestion invalide ou expirée. Les cotes exigent au plus deux décimales, entre 1,01 et 10,00.']);
        }
    }

    private function hundredths(mixed $value): int
    {
        // Decode the decimal representation; no multiplication/rounding in
        // floating point. Additional precision and exponent notation are refused.
        $text = is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
        if (! preg_match('/^(\d{1,2})(?:\.(\d{1,2}))?$/D', $text, $parts)) {
            throw ValidationException::withMessages(['pipeline' => 'Précision de cote invalide.']);
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    private function existing(array $sources): ?int
    {
        $ids = DB::table('markets')->whereIn('pipeline_source', $sources)->limit(2)->pluck('id');
        if ($ids->count() > 1) {
            throw ValidationException::withMessages(['pipeline' => 'Plusieurs imports historiques correspondent à cette source. Un rapprochement est nécessaire.']);
        }

        return $ids->isEmpty() ? null : (int) $ids->first();
    }
}
