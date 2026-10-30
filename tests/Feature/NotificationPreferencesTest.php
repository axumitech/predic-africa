<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_is_persisted_for_current_user_and_history_is_preserved(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notifier::send($user->id, 'Notification existante');
        $this->actingAs($user)->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('notificationsEnabled', true));
        $this->post('/notifications/preferences', ['notifications_enabled' => false, 'user_id' => $other->id])->assertRedirect()->assertSessionHasNoErrors();
        Notifier::send($user->id, 'Ne doit pas être reçue');
        Notifier::send($other->id, 'Autre utilisateur');
        $this->assertDatabaseCount('platform_notifications', 2);
        $this->assertFalse($user->fresh()->notifications_enabled);
        $this->assertTrue($other->fresh()->notifications_enabled);
        $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('notificationsEnabled', false)->has('notifications.data', 1)->where('unread', 1));
        $this->post('/notifications/read')->assertRedirect();
        $this->post('/notifications/preferences', ['notifications_enabled' => true])->assertSessionHasNoErrors();
        Notifier::send($user->id, 'Notification après réactivation');
        $this->assertSame(2, DB::table('platform_notifications')->where('user_id', $user->id)->count());
    }

    public function test_opt_out_does_not_block_financial_operations_or_journals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/notifications/preferences', ['notifications_enabled' => false])->assertSessionHasNoErrors();
        DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1000, true));
        $this->assertDatabaseCount('platform_notifications', 0);
        $this->assertDatabaseHas('payment_wallets', ['user_id' => $user->id, 'balance' => 1000]);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->artisan('ledger:reconcile')->assertSuccessful();
    }

    public function test_authentication_and_boolean_validation_are_required(): void
    {
        $this->post('/notifications/preferences', ['notifications_enabled' => false])->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/notifications/preferences', ['notifications_enabled' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('notifications_enabled');
        $this->postJson('/notifications/preferences', [])->assertUnprocessable()->assertJsonValidationErrors('notifications_enabled');
        $this->assertTrue($user->fresh()->notifications_enabled);
    }
}
