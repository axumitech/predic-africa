<?php

namespace App\Modules\Predictions\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PipelineClient
{
    public function generate(): void
    {
        $this->ensureAvailable();
        try {
            $response = $this->request()->timeout(15)->post($this->url().'/api/markets/generate')->throw();
            if ($response->json('status') !== 'success') {
                throw new RuntimeException('Invalid pipeline generation response.');
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->unavailable($exception);
        }
        Cache::forget($this->key('markets|'));
    }

    public function read(string $resource, ?string $domain): array
    {
        $key = $this->key($resource.'|'.$domain);
        // A cached successful result remains usable during a temporary outage.
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        $this->ensureAvailable();
        try {
            $response = $this->request()->timeout(max(1, (int) config('predictions.timeout')))
                ->get($this->url().'/api/'.$resource, array_filter(['domain' => $domain]))->throw();
            $data = $response->json('data');
            if (! is_array($data) || ! array_is_list($data)) {
                throw new RuntimeException('Invalid pipeline response.');
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->unavailable($exception);
        }
        $result = ['status' => 'success', 'data' => $data, 'simulation' => true];
        Cache::put($key, $result, max(1, (int) config('predictions.cache_seconds')));

        return $result;
    }

    private function request(): PendingRequest
    {
        // If a URL is accidentally pointed at Laravel, the API rejects this
        // header immediately instead of recursively proxying its own request.
        return Http::acceptJson()->withHeaders(['X-PredicAfrica-Pipeline' => '1'])
            ->connectTimeout(1)->withoutRedirecting();
    }

    private function ensureAvailable(): void
    {
        $retry = max(1, (int) config('predictions.retry_seconds'));
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
        return 'pipeline:v2:'.hash('sha256', $this->url().'|'.$resource);
    }
}
