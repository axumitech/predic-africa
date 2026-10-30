<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationRegionsTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $changes = []): array
    {
        return [...['name' => 'Compte régional', 'email' => 'region@example.test',
            'password' => 'regional-password-123', 'password_confirmation' => 'regional-password-123',
            'country' => 'GAB', 'currency' => 'XAF'], ...$changes];
    }

    public static function documentedPairs(): array
    {
        // NDSGPay's public “Correspondants disponibles” table, not assumed geography.
        return [
            'Bénin' => ['BEN', 'XOF'], 'Cameroun' => ['CMR', 'XAF'], 'Congo' => ['COG', 'XAF'],
            'Côte d’Ivoire' => ['CIV', 'XOF'], 'Gabon' => ['GAB', 'XAF'], 'Kenya' => ['KEN', 'KES'],
            'Ouganda' => ['UGA', 'UGX'], 'RDC' => ['COD', 'CDF'], 'Rwanda' => ['RWA', 'RWF'],
            'Sénégal' => ['SEN', 'XOF'], 'Sierra Leone' => ['SLE', 'SLE'], 'Zambie' => ['ZMB', 'ZMW'],
        ];
    }

    public function test_registration_catalogue_contains_the_documented_countries_and_no_payment_secrets(): void
    {
        config(['payments.secret_key' => 'must-stay-private']);
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Register')->has('countries', 12)
            ->where('countries.7.code', 'COD')->where('countries.7.currencies.0.code', 'CDF')
            ->missing('payments')->missing('secret_key'))
            ->assertDontSee('must-stay-private');
    }

    #[DataProvider('documentedPairs')]
    public function test_registration_persists_each_documented_pair_and_creates_an_empty_personal_wallet(string $country, string $currency): void
    {
        Http::fake();
        $this->post('/register', $this->registration(compact('country', 'currency')))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($country, $user->country);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => $currency, 'balance' => 0]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'balance' => 0]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->where('auth.country', $country)->where('auth.wallet_currency', $currency)->where('balance', 0));
        Http::assertNothingSent();
    }

    public static function invalidSelections(): array
    {
        return [
            'missing country' => [['country' => null], ['country', 'currency']],
            'missing currency' => [['currency' => null], ['currency']],
            'unsupported country' => [['country' => 'USA', 'currency' => 'USD'], ['country', 'currency']],
            'RDC with XAF' => [['country' => 'COD', 'currency' => 'XAF'], ['currency']],
            'Gabon with CDF' => [['currency' => 'CDF'], ['currency']],
            'XOF is distinct from XAF' => [['country' => 'BEN', 'currency' => 'XAF'], ['currency']],
            'unsupported USD' => [['country' => 'COD', 'currency' => 'USD'], ['currency']],
            'array country' => [['country' => ['GAB']], ['country', 'currency']],
            'array currency' => [['currency' => ['XAF']], ['currency']],
            'unrecognised casing' => [['country' => 'gab'], ['country', 'currency']],
        ];
    }

    #[DataProvider('invalidSelections')]
    public function test_missing_or_tampered_selection_cannot_create_an_account(array $changes, array $errors): void
    {
        $this->postJson('/register', $this->registration($changes))->assertUnprocessable()->assertJsonValidationErrors($errors);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, DB::table('accounts')->whereNotNull('user_id')->count());
        $this->assertDatabaseCount('payment_wallets', 0);
    }

    public function test_same_currency_in_different_countries_does_not_share_personal_balances(): void
    {
        $this->post('/register', $this->registration())->assertRedirect('/dashboard');
        $gabon = User::sole();
        $this->post('/logout');
        $this->post('/register', $this->registration(['country' => 'CMR', 'email' => 'cameroon@example.test']))->assertRedirect('/dashboard');
        $cameroon = User::where('email', 'cameroon@example.test')->firstOrFail();
        DB::transaction(fn () => app(Ledger::class)->transfer($gabon->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $gabon->id, 'currency' => 'XAF', 'balance' => 1000]);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $cameroon->id, 'currency' => 'XAF', 'balance' => 0]);
        $this->assertDatabaseCount('payment_wallets', 2);
    }

    public function test_profile_cannot_silently_switch_country_or_convert_the_wallet(): void
    {
        $this->post('/register', $this->registration(['country' => 'COD', 'currency' => 'CDF']));
        $user = User::sole();
        $this->post('/profile', ['name' => 'Nom modifié', 'email' => $user->email,
            'current_password' => 'regional-password-123', 'country' => 'GAB', 'currency' => 'XAF', 'wallet_currency' => 'XAF'])
            ->assertSessionHasNoErrors();
        $this->assertSame('COD', $user->fresh()->country);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
    }

    private function enableGabonPayments(): void
    {
        config(['payments.enabled' => true, 'payments.secret_key' => 'test-secret',
            'payments.webhook_secret' => 'test-hook', 'payments.merchant_id' => 'test-merchant',
            'payments.capabilities' => [['country' => 'GAB', 'currency' => 'XAF', 'correspondent' => 'AIRTEL_GAB',
                'calling_code' => '241', 'national_lengths' => [8]]]]);
        Http::fake();
    }

    #[DataProvider('incompatibleWallets')]
    public function test_signup_selection_filters_payment_options_and_blocks_incompatible_deposits(string $country, string $currency): void
    {
        $this->enableGabonPayments();
        $this->post('/register', $this->registration(compact('country', 'currency')));
        $this->get('/wallet')->assertInertia(fn (Assert $page) => $page
            ->where('ndsgpay.currency', $currency)->has('ndsgpay.correspondents', 0));
        $this->postJson('/wallet/ndsgpay', ['kind' => 'deposit', 'amount' => 1000, 'reference' => (string) Str::uuid(),
            'correspondent' => 'AIRTEL_GAB', 'msisdn' => '24177000000', 'country' => 'GAB', 'currency' => 'XAF'])
            ->assertUnprocessable()->assertJsonValidationErrors('correspondent');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_movements', 0);
        Http::assertNothingSent();
    }

    public static function incompatibleWallets(): array
    {
        return ['other country, same currency' => ['CMR', 'XAF'], 'other country and currency' => ['COD', 'CDF']];
    }

    public function test_a_cdf_wallet_cannot_be_debited_as_if_it_were_xaf(): void
    {
        $this->post('/register', $this->registration(['country' => 'COD', 'currency' => 'CDF']));
        $user = User::sole();
        $market = DB::table('markets')->insertGetId(['creator_id' => $user->id, 'title' => 'Un marché de référence en XAF',
            'description' => 'Un résultat vérifiable dans une source officielle.', 'category' => 'Sport',
            'status' => 'open', 'closes_at' => now()->addDay(), 'yes_odds' => 200, 'no_odds' => 200,
            'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/markets/'.$market.'/positions', ['outcome' => 'yes', 'amount' => 100, 'reference' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('quote_id');
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'CDF', 'balance' => 0]);
    }

    public function test_country_migration_preserves_existing_unknown_residence_and_xaf_funds(): void
    {
        $user = User::factory()->create();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
        $migration = require database_path('migrations/2026_10_29_000004_add_registration_country.php');
        $migration->down();
        $migration->up();
        $this->assertNull($user->fresh()->country);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'currency' => 'XAF', 'balance' => 1000]);
        $this->assertDatabaseHas('accounts', ['user_id' => $user->id, 'balance' => 1000]);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }
}
