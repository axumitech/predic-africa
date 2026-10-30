<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Money\ExactJson;
use App\Modules\Money\Money;
use App\Modules\Money\PositionQuotes;
use App\Modules\Money\RateBook;
use App\Modules\Money\RateSync;
use App\Modules\Payments\Payments;
use App\Modules\Settlement\ResolveMarket;
use App\Modules\Trading\PlacePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MulticurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config(['fx.key' => 'fx-secret']);
    }

    private function user(string $currency = 'CDF', string $country = 'COD'): User
    {
        $user = User::factory()->create(['country' => $country]);
        DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => $currency, 'balance' => 0]);
        return $user;
    }

    private function market(User $user): int
    {
        return DB::table('markets')->insertGetId(['creator_id' => $user->id, 'title' => 'Un marché multidevise vérifiable',
            'description' => 'Le résultat provient de la source officielle.', 'category' => 'Sport', 'status' => 'open',
            'closes_at' => now()->addHour(), 'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function rates(array $changes = []): void
    {
        DB::table('fx_snapshots')->insert(['provider' => 'currencyapi', 'base_currency' => 'XAF',
            'rates' => json_encode([...['XAF' => '1', 'CDF' => '4', 'XOF' => '1', 'KES' => '0.25',
                'UGX' => '7', 'RWF' => '2', 'ZMW' => '0.05', 'SLE' => '0.04'], ...$changes]),
            'as_of' => now()->startOfSecond(), 'fetched_at' => now()]);
    }

    private function fund(User $user, int $minor = 1000000): void
    {
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', $minor, true));
    }

    private function balance(User $user): int
    {
        return (int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance');
    }

    private function provider(array $overrides = [], int $status = 200): void
    {
        $data = [];
        foreach (['XAF' => '1', 'CDF' => '4.123456789012', 'XOF' => '1', 'KES' => '0.25', 'RWF' => '2', 'UGX' => '7', 'ZMW' => '0.05', 'SLE' => '0.04'] as $code => $value) {
            $data[$code] = ['code' => $code, 'value' => $value];
        }
        Http::fake(['api.currencyapi.com/*' => Http::response(array_replace_recursive([
            'meta' => ['base_currency' => 'XAF', 'last_updated_at' => now()->startOfSecond()->toIso8601String()],
            'data' => $data], $overrides), $status)]);
    }

    public function test_decimal_json_and_minor_units_never_pass_through_float_math(): void
    {
        $json = ExactJson::decode('{"amount":1234.56,"rate":4.123456789012345678,"id":9223372036854775808,"text":"1.234 and \\"2.0\\""}');
        $this->assertSame('1234.56', $json['amount']);
        $this->assertSame('4.123456789012345678', $json['rate']);
        $this->assertSame('9223372036854775808', $json['id']);
        $this->assertSame(123456, Money::minor($json['amount'], 'CDF'));
        $this->assertSame('1234.56', Money::decimal(123456, 'CDF'));
        $this->assertSame(97, Money::toBase(40000, 'CDF', '4.123456789012345678'));
    }

    public function test_sync_uses_private_key_header_preserves_precision_and_throttles_repeated_requests(): void
    {
        $this->provider();
        $this->assertTrue(app(RateSync::class)->refresh());
        $this->assertFalse(app(RateSync::class)->refresh());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('apikey', 'fx-secret')
            && $request['base_currency'] === 'XAF' && str_contains($request['currencies'], 'SLE'));
        $view = app(RateBook::class)->fresh('CDF');
        $this->assertSame('4.123456789012000000', $view['rate']);
        $this->assertSame('412.35', $view['min_stake']);
        $this->assertDatabaseCount('fx_snapshots', 1);
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertDontSee('fx-secret')->assertInertia(fn (Assert $page) => $page
            ->where('fx.currency', 'CDF')->where('fx.available', true)->missing('fx.key'));
        $this->getJson('/fx/rates')->assertOk()->assertJsonPath('currency', 'CDF')->assertJsonMissing(['key' => 'fx-secret']);
        Http::assertSentCount(1); // Page and browser polling never fetch the external source.
    }

    public static function invalidRates(): array
    {
        return [
            'wrong base' => [['meta' => ['base_currency' => 'USD']]],
            'negative rate' => [['data' => ['CDF' => ['value' => '-4']]]],
            'zero rate' => [['data' => ['CDF' => ['value' => '0']]]],
            'wrong code' => [['data' => ['CDF' => ['code' => 'USD']]]],
            'missing timestamp' => [['meta' => ['last_updated_at' => null]]],
        ];
    }

    #[DataProvider('invalidRates')]
    public function test_invalid_source_response_cannot_publish_a_rate(array $changes): void
    {
        $this->provider($changes);
        $this->assertFalse(app(RateSync::class)->refresh());
        $this->assertFalse(app(RateBook::class)->view('CDF')['available']);
        $this->assertDatabaseCount('fx_snapshots', 0);
    }

    public function test_stale_future_rate_quota_and_absent_key_fail_closed_without_erasing_history(): void
    {
        config(['fx.refresh_seconds' => 60]);
        Http::fake(['api.currencyapi.com/*' => Http::sequence()
            ->push(['meta' => ['base_currency' => 'XAF', 'last_updated_at' => now()->subSeconds(config('fx.max_age_seconds') + 1)->toIso8601String()]])
            ->push(['meta' => ['base_currency' => 'XAF', 'last_updated_at' => now()->addMinutes(3)->toIso8601String()]])
            ->push([], 429)]);
        $this->assertFalse(app(RateSync::class)->refresh());
        $this->assertSame('stale_source', DB::table('fx_sync_state')->value('last_error'));
        $this->travel(config('fx.refresh_seconds') + 1)->seconds();
        $this->assertFalse(app(RateSync::class)->refresh());
        $this->travel(config('fx.refresh_seconds') + 1)->seconds();
        $this->assertFalse(app(RateSync::class)->refresh());
        $this->assertSame('rate_limited', DB::table('fx_sync_state')->value('last_error'));
        $this->assertFalse(app(RateSync::class)->refresh());
        config(['fx.key' => null]);
        $this->assertFalse(app(RateSync::class)->refresh());
        $this->assertTrue(app(RateBook::class)->view('XAF')['available']);
    }

    public function test_no_stale_or_missing_rate_can_create_a_foreign_position_or_quote(): void
    {
        $user = $this->user();
        $market = $this->market($user);
        $this->fund($user);
        $this->actingAs($user)->postJson('/markets/'.$market.'/quote', ['amount' => '400.00', 'outcome' => 'yes', 'reference' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('quote_id');
        $this->rates();
        DB::table('fx_snapshots')->update(['as_of' => now()->subSeconds(config('fx.max_age_seconds') + 1)]);
        $this->postJson('/markets/'.$market.'/quote', ['amount' => '400.00', 'outcome' => 'yes', 'reference' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('quote_id');
        $this->assertSame(1000000, $this->balance($user));
        $this->assertDatabaseCount('fx_quotes', 0);
        Http::assertNothingSent();
    }

    public function test_daily_rate_is_accepted_throttled_and_pinned_to_confirmation(): void
    {
        $this->freezeSecond();
        $this->assertSame(86400, config('fx.refresh_seconds'));
        $this->assertSame(172800, config('fx.max_age_seconds'));
        $source = now()->subHours(23);
        $this->provider(['meta' => ['last_updated_at' => $source->toIso8601String()]]);
        $this->assertTrue(app(RateSync::class)->refresh());
        $this->travel(1)->hours();
        $this->assertFalse(app(RateSync::class)->refresh());
        Http::assertSentCount(1);
        $user = $this->user();
        $market = $this->market($user);
        $this->fund($user);
        $quote = app(PositionQuotes::class)->create($user, $market, 'yes', '500', (string) Str::uuid());
        $this->rates(['CDF' => '8']);
        app(PlacePosition::class)->place($user->id, $market, 'yes', '500', $quote->reference, $quote->id);
        $saved = DB::table('fx_quotes')->where('id', $quote->id)->sole();
        $this->assertSame($quote->rate, $saved->rate);
        $this->assertSame($source->utc()->format('Y-m-d H:i:s'), $saved->as_of);
        $this->assertSame(950000, $this->balance($user));
        $this->travel(48)->hours();
        $this->assertFalse(app(RateBook::class)->view('CDF')['available']);
    }

    #[DataProvider('nativeCurrencies')]
    public function test_each_currency_can_fund_participate_and_settle_in_its_own_minor_units(string $country, string $currency, string $stake, int $stakeMinor, int $payoutMinor): void
    {
        $user = $this->user($currency, $country);
        $market = $this->market($user);
        $this->rates();
        $this->fund($user);
        $reference = (string) Str::uuid();
        $quote = app(PositionQuotes::class)->create($user, $market, 'yes', $stake, $reference);
        $this->actingAs($user)->postJson('/markets/'.$market.'/positions', ['amount' => $stake, 'outcome' => 'yes', 'reference' => $reference, 'quote_id' => $quote->id])
            ->assertRedirect('/positions');
        $this->assertSame(1000000 - $stakeMinor, $this->balance($user));
        $this->assertDatabaseHas('positions', ['wallet_currency' => $currency, 'wallet_stake' => $stakeMinor, 'wallet_payout' => $payoutMinor, 'stake' => 100]);
        $this->travel(2)->hours(); // Rate and quote have expired; settlement still uses immutable native amounts.
        app(ResolveMarket::class)->resolve($market, 'yes', 'Source officielle du résultat');
        $this->assertSame(1000000 - $stakeMinor + $payoutMinor, $this->balance($user));
        $this->postJson('/markets/'.$market.'/positions', ['amount' => $stake, 'outcome' => 'yes', 'reference' => $reference, 'quote_id' => $quote->id])->assertRedirect();
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseCount('ledger_transactions', 3);
        $this->artisan('ledger:reconcile')->assertSuccessful();
        Http::assertNothingSent();
    }

    public static function nativeCurrencies(): array
    {
        return ['CDF' => ['COD', 'CDF', '400.00', 40000, 94000], 'XOF' => ['BEN', 'XOF', '100', 100, 235],
            'KES' => ['KEN', 'KES', '25.00', 2500, 5875], 'RWF' => ['RWA', 'RWF', '200', 200, 470],
            'UGX' => ['UGA', 'UGX', '700', 700, 1645], 'ZMW' => ['ZMB', 'ZMW', '5.00', 500, 1175],
            'SLE' => ['SLE', 'SLE', '4.00', 400, 940]];
    }

    public function test_quote_is_bound_to_owner_intent_price_expiration_and_cannot_be_consumed_twice(): void
    {
        $user = $this->user();
        $other = $this->user();
        $market = $this->market($user);
        $this->rates();
        $this->fund($user);
        $reference = (string) Str::uuid();
        $quote = app(PositionQuotes::class)->create($user, $market, 'yes', '400.00', $reference);
        $body = ['amount' => '400.00', 'outcome' => 'yes', 'reference' => $reference, 'quote_id' => $quote->id];
        $this->actingAs($other)->postJson('/markets/'.$market.'/positions', $body)->assertUnprocessable();
        $this->actingAs($user);
        foreach ([['amount' => '800.00'], ['outcome' => 'no'], ['reference' => (string) Str::uuid()]] as $changes) {
            $this->postJson('/markets/'.$market.'/positions', [...$body, ...$changes])->assertUnprocessable()->assertJsonValidationErrors('quote_id');
        }
        DB::table('markets')->where('id', $market)->update(['yes_odds' => 236]);
        $this->postJson('/markets/'.$market.'/positions', $body)->assertUnprocessable();
        DB::table('markets')->where('id', $market)->update(['yes_odds' => 235]);
        $this->travel(60)->seconds();
        $this->postJson('/markets/'.$market.'/positions', $body)->assertUnprocessable()->assertJsonValidationErrors('quote_id');
        $this->assertSame(1000000, $this->balance($user));
        $this->assertDatabaseCount('positions', 0);
    }

    public function test_rounded_native_winnings_and_full_refund_do_not_depend_on_later_rates(): void
    {
        $user = $this->user();
        $market = $this->market($user);
        $this->rates();
        $this->fund($user);
        $reference = (string) Str::uuid();
        $quote = app(PositionQuotes::class)->create($user, $market, 'yes', '400.01', $reference);
        $this->assertSame(94002, $quote->wallet_payout); // Exact floor in CDF cents.
        $this->actingAs($user)->postJson('/markets/'.$market.'/positions', ['amount' => '400.01', 'outcome' => 'yes', 'reference' => $reference, 'quote_id' => $quote->id])->assertRedirect();
        $this->travel(10)->minutes();
        $this->rates(['CDF' => '8']);
        app(ResolveMarket::class)->resolve($market, 'void', 'Annulation officielle du marché');
        app(ResolveMarket::class)->resolve($market, 'void', 'Rejeu du même résultat officiel');
        $this->assertSame(1000000, $this->balance($user));
        $this->assertDatabaseCount('ledger_transactions', 3);
    }

    public function test_currency_treasuries_are_separate_and_reconciliation_detects_cross_currency_entries(): void
    {
        $cdf = $this->user();
        $xaf = $this->user('XAF', 'GAB');
        $this->fund($cdf, 40000);
        $this->fund($xaf, 100);
        $this->assertDatabaseHas('accounts', ['code' => 'treasury:CDF', 'currency' => 'CDF', 'balance' => -40000]);
        $this->assertDatabaseHas('accounts', ['code' => 'treasury', 'currency' => 'XAF', 'balance' => -100]);
        $this->artisan('ledger:reconcile')->assertSuccessful();
        DB::table('ledger_entries')->where('currency', 'CDF')->update(['currency' => 'XAF']);
        $this->artisan('ledger:reconcile')->assertFailed();
    }

    public function test_confirmed_cdf_deposit_is_native_exact_and_idempotent_even_without_fx_source(): void
    {
        $user = $this->user();
        config(['fx.key' => null, 'payments.enabled' => true, 'payments.secret_key' => 'payment-secret',
            'payments.webhook_secret' => 'hook-secret', 'payments.merchant_id' => 'merchant-test',
            'payments.capabilities' => [['country' => 'COD', 'currency' => 'CDF', 'correspondent' => 'AIRTEL_COD',
                'calling_code' => '243', 'national_lengths' => [9]]]]);
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'cdf-1', 'status' => 'INITIATED']),
            '*/deposits/cdf-1' => Http::response('{"success":true,"deposit":{"depositId":"cdf-1","amount":1234.56,"currency":"CDF","status":"COMPLETED"}}', 200, ['Content-Type' => 'application/json'])]);
        $body = ['kind' => 'deposit', 'amount' => '1234.56', 'reference' => (string) Str::uuid(),
            'correspondent' => 'AIRTEL_COD', 'msisdn' => '243810000000'];
        $this->actingAs($user)->post('/wallet/ndsgpay', $body)->assertSessionHasNoErrors();
        $this->post('/wallet/ndsgpay', $body)->assertSessionHasNoErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === '1234.56' && $request['currency'] === 'CDF');
        $payment = DB::table('payments')->sole();
        $this->assertSame(123456, $payment->amount);
        app(Payments::class)->refresh($payment);
        $this->assertSame(123456, $this->balance($user));
        app(Payments::class)->refresh($payment);
        $this->assertDatabaseCount('payment_movements', 1);
        $this->assertDatabaseHas('ledger_transactions', ['currency' => 'CDF', 'amount' => 123456]);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    #[DataProvider('nativeCurrencies')]
    public function test_other_confirmed_native_deposits_use_major_units_at_the_provider_and_minor_units_in_the_ledger(string $country, string $currency, string $_stake, int $_minor, int $_payout): void
    {
        $user = $this->user($currency, $country);
        $major = Money::scale($currency) ? '1234.56' : '1234';
        $minor = Money::minor($major, $currency);
        // Explicitly fictional operator and phone pattern, confined to this test.
        $operator = 'CONFIRMED_TEST_'.$country;
        config(['payments.enabled' => true, 'payments.secret_key' => 'test-secret', 'payments.webhook_secret' => 'hook',
            'payments.merchant_id' => 'merchant', 'payments.capabilities' => [['country' => $country,
                'currency' => $currency, 'correspondent' => $operator, 'calling_code' => '999', 'national_lengths' => [6]]]]);
        Http::fake(['*/pay/deposits' => Http::response(['success' => true, 'depositId' => 'native-1', 'status' => 'INITIATED']),
            '*/deposits/native-1' => Http::response(['success' => true, 'deposit' => ['depositId' => 'native-1',
                'amount' => $major, 'currency' => $currency, 'status' => 'COMPLETED']])]);
        $this->actingAs($user)->post('/wallet/ndsgpay', ['kind' => 'deposit', 'amount' => $major,
            'reference' => (string) Str::uuid(), 'correspondent' => $operator, 'msisdn' => '999123456', 'currency' => 'USD'])
            ->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['amount'] === $major && $request['currency'] === $currency);
        app(Payments::class)->refresh(DB::table('payments')->sole());
        $this->assertSame($minor, $this->balance($user));
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_native_aggregates_are_not_summed_with_base_amounts_and_old_quote_survives_key_rotation(): void
    {
        $user = $this->user();
        $market = $this->market($user);
        $this->rates();
        $this->fund($user);
        $reference = (string) Str::uuid();
        $quote = app(PositionQuotes::class)->create($user, $market, 'yes', '400.00', $reference);
        $this->actingAs($user)->postJson('/markets/'.$market.'/positions', ['amount' => '400.00', 'outcome' => 'yes',
            'reference' => $reference, 'quote_id' => $quote->id])->assertRedirect();
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('stats.engaged', 40000)->where('stats.potential', 94000));
        $this->get('/markets')->assertInertia(fn (Assert $page) => $page->where('stats.volume', 100));
        $old = config('app.key');
        config(['app.previous_keys' => [$old], 'app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
        app(ResolveMarket::class)->resolve($market, 'yes', 'Résultat officiel après rotation');
        $this->assertSame(1054000, $this->balance($user));
        $this->get('/markets')->assertInertia(fn (Assert $page) => $page->where('stats.paid', 235));
    }
}
