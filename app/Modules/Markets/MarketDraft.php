<?php

namespace App\Modules\Markets;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class MarketDraft
{
    public static function revision(object $market): string
    {
        return hash('sha256', json_encode([
            $market->id, $market->status, $market->title, $market->description, $market->category,
            $market->closes_at, $market->source_closes_at ?? null, $market->yes_odds, $market->no_odds, $market->updated_at,
            $market->event_ends_at ?? null, $market->resolution_rule ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    public static function validated(array $input): array
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }
        $data = Validator::make($input, [
            'title' => 'required|string|min:10|max:200', 'description' => 'required|string|min:20|max:5000',
            'category' => ['required', Rule::in(['Sport', 'Politique', 'Musique', 'Économie', 'Crypto', 'Société'])],
            'closes_at' => 'required|date|after:now',
            'yes_odds' => 'required|integer|min:101|max:1000', 'no_odds' => 'required|integer|min:101|max:1000',
        ])->validate();
        $data['closes_at'] = Carbon::parse($data['closes_at'])->utc();

        return $data;
    }
}
