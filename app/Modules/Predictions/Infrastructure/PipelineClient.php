<?php

namespace App\Modules\Predictions\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PipelineClient
{
    public function generate(): void
    {
        $this->ensureAvailable();
        try {
            $response = $this->request()->timeout(15)->post($this->url().'/api/markets/generate')->throw();
            if (! $response->successful() || $response->json('status') !== 'success' || $response->json('simulation') !== false) {
                throw new RuntimeException('Invalid pipeline generation response.');
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->unavailable($exception);
        }
        Cache::forever($this->key('markets-revision'), (string) Str::uuid());
    }

    public function read(string $resource, ?string $domain, bool $fresh = false): array
    {
        if (! config('predictions.enabled')) {
            throw new PipelineUnavailable(max(1, (int) config('predictions.retry_seconds')));
        }
        if (! in_array($resource, ['markets', 'predictions'], true)) {
            throw new \InvalidArgumentException('Unsupported pipeline resource.');
        }
        $revision = $resource === 'markets' ? Cache::get($this->key('markets-revision'), '0') : '0';
        $key = $this->key($resource.'|'.$domain.'|'.$revision);
        // A cached successful result remains usable during a temporary outage.
        if (! $fresh && ($cached = Cache::get($key)) && ($cached['simulation'] ?? true) === false) {
            return $cached;
        }
        $this->ensureAvailable();
        try {
            $response = $this->request()->timeout(max(1, (int) config('predictions.timeout')))
                ->get($this->url().'/api/'.$resource, array_filter(['domain' => $domain]))->throw();
            $data = $response->json('data');
            if (! $response->successful() || $response->json('status', 'success') !== 'success' || $response->json('simulation') !== false
                || ! is_array($data) || ! array_is_list($data) || count($data) > 100) {
                throw new RuntimeException('Invalid pipeline response.');
            }
            $ids = [];
            foreach ($data as $item) {
                if (! is_array($item) || ! is_int($item['id'] ?? null) || $item['id'] <= 0 || isset($ids[$item['id']])) {
                    throw new RuntimeException('Invalid or duplicate pipeline item.');
                }
                $ids[$item['id']] = true;
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->unavailable($exception);
        }
        $result = ['status' => 'success', 'data' => $data, 'simulation' => false, 'fetched_at' => now()->toIso8601String()];
        Cache::put($key, $result, max(1, (int) config('predictions.cache_seconds')));

        return $result;
    }

    public function sourceKeys(int $id): array
    {
        $url = $this->url();

        return array_values(array_unique(array_map(fn ($base) => hash('sha256', $base).':'.$id,
            [$url, (string) config('predictions.url'), $url.'/'])));
    }

    private function request(): PendingRequest
    {
        // If a URL is accidentally pointed at Laravel, the API rejects this
        // header immediately instead of recursively proxying its own request.
        return Http::acceptJson()->withHeaders(array_filter(['X-PredicAfrica-Pipeline' => '1', 'X-Pipeline-Token' => config('predictions.token')]))
            ->connectTimeout(1)->withoutRedirecting();
    }

    private function ensureAvailable(): void
    {
        $retry = max(1, (int) config('predictions.retry_seconds'));
        $parts = parse_url($this->url());
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            $this->unavailable(new RuntimeException('Invalid pipeline URL configuration.'));
        }
        if (! config('predictions.enabled')) {
            throw new PipelineUnavailable($retry);
        }
        $until = Cache::get($this->key('unavailable'));
        if ($until && $until > now()->timestamp) {
            throw new PipelineUnavailable($until - now()->timestamp);
        }
        // Avoid a deadlock before opening a connection to this HTTP server.
        if (app()->bound('request')) {
            $request = request();
            $host = parse_url($this->url(), PHP_URL_HOST);
            $port = parse_url($this->url(), PHP_URL_PORT) ?: (str_starts_with($this->url(), 'https:') ? 443 : 80);
            $loopback = ['localhost', '127.0.0.1', '::1'];
            if ($port === $request->getPort() && ($host === $request->getHost() || (in_array($host, $loopback) && in_array($request->getHost(), $loopback)))) {
                $this->unavailable(new RuntimeException('AI_PIPELINE_URL points to the Laravel HTTP server.'));
            }
        }
    }

    private function unavailable(\Throwable $exception): never
    {
        $retry = max(1, (int) config('predictions.retry_seconds'));
        if (Cache::add($this->key('unavailable'), now()->timestamp + $retry, $retry)) {
            // Expected optional-service outage: one concise warning per pause,
            // without a stack trace or private upstream response in the logs.
            Log::warning('Pipeline IA indisponible ; nouvelle tentative après {seconds}s.', [
                'seconds' => $retry,
                'reason' => $exception instanceof ConnectionException ? 'connection' : ($exception instanceof RequestException ? 'http' : 'configuration_or_response'),
            ]);
        }
        throw new PipelineUnavailable($retry);
    }

    private function url(): string
    {
        return rtrim((string) config('predictions.url'), '/');
    }

    private function key(string $resource): string
    {
        return 'pipeline:v3:'.hash('sha256', $this->url().'|'.$resource);
    }
}
