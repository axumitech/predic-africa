<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PredictionApiTest extends TestCase
{
    public function test_legacy_demo_remains_available(): void
    {
        $this->withoutVite()->get('/demo')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Prototype', false)->where('mode', 'demo'));
    }

    public function test_pipeline_is_proxied_and_cached(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/api/predictions*' => Http::response(['data' => [['id' => 42, 'domain' => 'football']]])]);

        $this->getJson('/api/predictions?domain=football')->assertOk()->assertJsonPath('data.0.id', 42);
        $this->getJson('/api/predictions?domain=football')->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['domain'] === 'football');
    }

    public function test_unavailable_pipeline_does_not_break_the_demo(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->getJson('/api/markets')->assertStatus(503)->assertJsonPath('data', [])
            ->assertJsonPath('status', 'unavailable');
        $this->withoutVite()->get('/')->assertOk();
    }

    public function test_malformed_pipeline_response_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['data' => 'invalid'])]);
        $this->getJson('/api/predictions')->assertStatus(503);
    }

    public function test_domain_is_validated_before_calling_pipeline(): void
    {
        Http::fake();
        $this->getJson('/api/predictions?domain[]=football')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_outage_is_shared_between_endpoints_and_recovers_after_the_pause(): void
    {
        $this->freezeTime();
        Log::spy();
        Http::fakeSequence()->pushStatus(503)->push(['data' => [['id' => 42]]]);
        $this->getJson('/api/predictions')->assertStatus(503)->assertHeader('Retry-After', '30');
        $this->getJson('/api/markets')->assertStatus(503);
        $this->getJson('/api/predictions?domain=football')->assertStatus(503);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('error');
        $this->travel(31)->seconds();
        $this->getJson('/api/markets')->assertOk()->assertJsonPath('data.0.id', 42);
        Http::assertSentCount(2);
    }

    public function test_connection_timeouts_are_not_retried_on_every_request(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Timed out');
        });
        $this->getJson('/api/predictions')->assertStatus(503);
        $this->getJson('/api/markets')->assertStatus(503);
        $this->assertSame(1, $attempts);
    }

    public function test_proxy_requests_to_laravel_never_recurse(): void
    {
        Http::fake();
        $this->withHeader('X-PredicAfrica-Pipeline', '1')->getJson('/api/predictions')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_a_pipeline_url_pointing_to_the_current_server_is_rejected_before_connecting(): void
    {
        config(['predictions.url' => 'http://localhost']);
        Http::fake();
        $this->getJson('http://localhost/api/markets')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_disabled_pipeline_never_opens_a_connection(): void
    {
        config(['predictions.enabled' => false]);
        Http::fake();
        $this->getJson('/api/predictions')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_cache_is_scoped_to_the_pipeline_url(): void
    {
        Http::fakeSequence()->push(['data' => [['id' => 1]]])->push(['data' => [['id' => 2]]]);
        $this->getJson('/api/markets')->assertJsonPath('data.0.id', 1);
        config(['predictions.url' => 'http://127.0.0.1:8102']);
        $this->getJson('/api/markets')->assertJsonPath('data.0.id', 2);
        Http::assertSentCount(2);
    }
}
