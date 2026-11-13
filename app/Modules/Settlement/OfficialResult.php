<?php

namespace App\Modules\Settlement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OfficialResult
{
    public function check(object $market): array
    {
        $rule = json_decode($market->resolution_rule, true, flags: JSON_THROW_ON_ERROR);
        $observations = [];
        $outcomes = [];
        foreach ($rule['providers'] as $provider) {
            $endpoint = config("market_resolution.providers.$provider");
            if (! is_string($endpoint) || parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || ! parse_url($endpoint, PHP_URL_HOST)) {
                throw new RuntimeException('Adaptateur de résultat indisponible.');
            }
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)->withoutRedirecting()
                ->get($endpoint, ['event' => $rule['event'], 'condition' => $rule['condition']])->throw();
            if (! $response->successful()) {
                throw new RuntimeException('Réponse inattendue de l’adaptateur.');
            }
            $data = $response->json();
            if (! is_array($data) || ($data['event'] ?? null) !== $rule['event']
                || ($data['condition'] ?? null) !== $rule['condition']) {
                throw new RuntimeException('Attestation hors contrat.');
            }
            if (in_array($data['status'] ?? null, ['pending', 'postponed'], true)) {
                continue;
            }
            $status = $data['status'] ?? null;
            $value = $data['value'] ?? null;
            $outcome = match ($status) {
                'final' => $value === $rule['yes_value'] ? 'yes' : ($value === $rule['no_value'] ? 'no' : null),
                'cancelled' => $rule['on_cancel'],
                'tie' => $rule['on_tie'],
                default => null,
            };
            if ($outcome === null || ! is_string($data['official_url'] ?? null)
                || strlen($data['official_url']) > 1000 || ! filter_var($data['official_url'], FILTER_VALIDATE_URL)
                || parse_url($data['official_url'], PHP_URL_SCHEME) !== 'https'
                || ! is_string($data['verified_at'] ?? null)
                || ! preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $data['verified_at'])) {
                throw new RuntimeException('Attestation officielle incomplète.');
            }
            $verified = CarbonImmutable::parse($data['verified_at']);
            if ($verified->format('Y-m-d\TH:i:s\Z') !== $data['verified_at'] || $verified->lt(CarbonImmutable::parse($market->event_ends_at)) || $verified->lt(now()->subHour()) || $verified->gt(now())) {
                throw new RuntimeException('Attestation officielle hors fenêtre de vérification.');
            }
            $outcomes[] = $outcome;
            $observations[] = ['provider' => $provider, 'event' => $rule['event'], 'condition' => $rule['condition'],
                'status' => $status, 'value' => is_string($value) && strlen($value) <= 100 ? $value : null,
                'url' => $data['official_url'], 'verified_at' => $data['verified_at']];
        }

        if (count(array_unique($outcomes)) > 1) {
            return ['disputed', $observations];
        }

        return [count($outcomes) === count($rule['providers']) ? $outcomes[0] : 'undetermined', $observations];
    }
}
