<?php

namespace App\Modules\Payments;

use App\Modules\Money\ExactJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Ndsgpay
{
    public static function validId(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z/D', $id) === 1;
    }

    public static function description(string $value): string
    {
        return substr(trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($value)))), 0, 22);
    }

    public function ready(): bool
    {
        return (bool) (config('payments.enabled') && config('payments.secret_key') && config('payments.webhook_secret'));
    }

    // Local account scope only. Never derive it from an unsigned webhook header.
    public static function merchantScope(): ?string
    {
        $merchant = config('payments.merchant_id');

        return is_string($merchant) && trim($merchant) !== '' ? trim($merchant) : null;
    }

    public function request(string $method, string $path, array $payload = []): array
    {
        $base = rtrim(config('payments.base_url'), '/');
        $url = parse_url($base);
        if (! $this->ready() || ($url['scheme'] ?? null) !== 'https' || empty($url['host'])
            || (isset($url['user']) || isset($url['pass'])) || isset($url['query']) || isset($url['fragment'])
            || ($url['path'] ?? '') !== '/api') {
            throw new GatewayFailure('configuration', false);
        }
        if (! (($method === 'POST' && in_array($path, ['/pay/deposits', '/pay/payouts'], true))
            || ($method === 'GET' && preg_match('~\A/(deposits|payouts)/[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z~D', $path)))) {
            throw new GatewayFailure('invalid_path', false);
        }
        app(ProviderBudget::class)->acquire($method);
        try {
            // TLS verified, no redirects, no automatic POST retry, fixed configuration host.
            $response = Http::withToken(config('payments.secret_key'))->acceptJson()->asJson()
                ->withoutRedirecting()->withOptions(['verify' => true])
                ->connectTimeout(config('payments.connect_timeout'))->timeout(config('payments.timeout'))
                ->send($method, $base.$path, $method === 'POST' ? ['json' => $payload] : []);
        } catch (ConnectionException $exception) {
            throw new GatewayFailure('network');
        }
        if ($response->status() === 429) {
            $value = $response->header('Retry-After');
            $seconds = ctype_digit($value) ? (int) $value : max(1, (strtotime($value) ?: time() + 60) - time());
            $seconds = min(86400, max(1, $seconds));
            app(ProviderBudget::class)->defer($method, $seconds);
            throw new GatewayFailure('rate_limited', true, $seconds);
        }
        if (! $response->successful()) {
            throw new GatewayFailure(match ($response->status()) {
                400 => 'rejected', 401 => 'authentication', 403 => 'activation_or_permission', 404 => 'not_found',
                default => $response->status() >= 500 ? 'unavailable' : 'unexpected_http',
            });
        }
        try {
            $json = ExactJson::decode($response->body());
        } catch (\Throwable) {
            throw new GatewayFailure('invalid_response');
        }
        if (! is_array($json) || ($json['success'] ?? null) !== true) {
            throw new GatewayFailure('invalid_response');
        }

        return $json;
    }
}
