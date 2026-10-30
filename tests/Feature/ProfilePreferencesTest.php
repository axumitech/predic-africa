<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWallets(): User
    {
        $user = User::factory()->create(['country' => null, 'phone' => '+24177000000']);
        $this->actingAs($user)->post('/wallets', ['currency' => 'XAF'])->assertSessionHasNoErrors();
        $this->post('/wallets', ['currency' => 'CDF'])->assertSessionHasNoErrors();
        return $user;
    }

    private function data(User $user, array $changes = []): array
    {
        return [...['name' => $user->name, 'email' => $user->email, 'current_password' => 'password'], ...$changes];
    }

    public function test_profile_can_fill_missing_country_without_changing_wallets_or_phone(): void
    {
        $user = $this->userWithWallets();
        $this->post('/profile', $this->data($user, ['country' => 'GAB']))->assertSessionHasNoErrors();
        $this->assertSame('GAB', $user->fresh()->country);
        $this->assertSame('+24177000000', $user->fresh()->phone);
        $this->assertDatabaseCount('payment_wallets', 2);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active_wallet_currency' => 'CDF']);
        $this->post('/profile', $this->data($user, ['country' => 'COD', 'phone' => '+243810000000']))->assertSessionHasNoErrors();
        $this->assertSame('GAB', $user->fresh()->country);
        $this->assertSame('+24177000000', $user->fresh()->phone);
    }

    public function test_country_and_default_wallet_validation_require_a_valid_country_and_owned_wallet(): void
    {
        $user = $this->userWithWallets();
        $this->postJson('/profile', $this->data($user, ['country' => 'UNKNOWN']))->assertUnprocessable()->assertJsonValidationErrors('country');
        $other = User::factory()->create();
        DB::table('payment_wallets')->insert(['user_id' => $other->id, 'currency' => 'KES', 'balance' => 0]);
        $this->postJson('/profile', $this->data($user, ['country' => 'GAB', 'default_wallet_currency' => 'KES']))->assertUnprocessable()->assertJsonValidationErrors('default_wallet_currency');
        $this->assertNull($user->fresh()->country);
        $this->assertNull($user->fresh()->default_wallet_currency);
        $this->post('/profile', $this->data($user, ['country' => 'GAB', 'default_wallet_currency' => 'XAF', 'current_password' => 'wrong']))->assertSessionHasErrors('current_password');
        $this->assertNull($user->fresh()->country);
        $this->assertNull($user->fresh()->default_wallet_currency);
    }

    public function test_default_is_applied_on_login_and_does_not_follow_temporary_switches(): void
    {
        $user = $this->userWithWallets();
        $this->post('/profile', $this->data($user, ['default_wallet_currency' => 'XAF']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'active_wallet_currency' => 'CDF', 'default_wallet_currency' => 'XAF']);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get('/profile')->assertInertia(fn (Assert $page) => $page->where('auth.wallet_currency', 'XAF')->where('profileDetails.default_wallet_currency', 'XAF'));
        $this->post('/wallets/select', ['currency' => 'CDF'])->assertSessionHasNoErrors();
        $this->assertSame('XAF', $user->fresh()->default_wallet_currency);
    }

    public function test_phone_is_exposed_only_on_owners_profile_and_default_falls_back_to_first_wallet(): void
    {
        $user = $this->userWithWallets();
        $this->get('/profile')->assertInertia(fn (Assert $page) => $page->where('profileDetails.phone', '+24177000000')
            ->where('profileDetails.default_wallet_currency', 'XAF')->missing('auth.phone'));
        $this->get('/wallet')->assertInertia(fn (Assert $page) => $page->missing('profileDetails')->missing('auth.phone'));
        $other = User::factory()->create();
        $this->actingAs($other)->get('/profile?user_id='.$user->id)->assertInertia(fn (Assert $page) => $page->where('profileDetails.phone', null));
    }
}
