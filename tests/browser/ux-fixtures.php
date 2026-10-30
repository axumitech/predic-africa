<?php

// Only the audit runner's ephemeral database may receive these fixtures.
use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Trading\PlacePosition;
use App\Modules\Settlement\ResolveMarket;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Explicit test environment required.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_contains(config('database.connections.sqlite.database'), 'predic-e2e-ux-') || $app->configurationIsCached()) {
    throw new RuntimeException('Refusing to write outside the isolated UX database.');
}
$trader = User::factory()->create(['name' => 'Aïcha Ndiaye — Observatrice des marchés et des grands événements africains', 'email' => 'ux-trader@example.test', 'password' => 'ux-password-123']);
$admin = User::factory()->create(['name' => 'Administration PredicAfrica', 'email' => 'ux-admin@example.test', 'password' => 'ux-password-123', 'role' => 'admin']);
User::factory()->create(['name' => 'Nouveau compte', 'email' => 'ux-empty@example.test', 'password' => 'ux-password-123']);
User::factory()->create(['name' => 'Compte suspendu', 'email' => 'suspended@example.test', 'suspended' => true]);
User::factory()->create(['name' => str_repeat('NomLong', 12), 'email' => str_repeat('a', 55).'@'.str_repeat('b', 50).'.example.test']);
DB::transaction(fn () => app(Ledger::class)->transfer($trader->id, (string) Str::uuid(), 'Dépôt NDSGPay', 1250000, true));
$market = function (string $title, string $status = 'open', string $category = 'Sport') use ($admin) {
    return DB::table('markets')->insertGetId(['creator_id' => $admin->id, 'title' => $title,
        'description' => 'Oui si le résultat officiel publié à la clôture confirme les conditions annoncées. Non dans le cas contraire. Source : publication officielle de l’organisateur. '.str_repeat('Les critères de résolution et les dates doivent rester lisibles même lorsque les informations sont détaillées. ', 12),
        'category' => $category, 'status' => $status, 'closes_at' => now()->addDays(3), 'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now()]);
};
$open = $market('Le programme régional atteindra-t-il son objectif annoncé avant la fin de l’année ?');
$long = $market('Le résultat de '.str_repeat('ÉvénementInternational', 7).' sera-t-il confirmé avant la date limite ?', 'open', 'Économie');
$pending = $market('Cette proposition vérifiable sera-t-elle publiée après examen des sources ?', 'pending', 'Société');
$won = $market('Le résultat officiel sera-t-il publié dans les délais prévus ?', 'open', 'Politique');
$void = $market('Le prochain rendez-vous culturel maintiendra-t-il sa programmation ?', 'open', 'Musique');
foreach ([$open, $won, $void] as $id) app(PlacePosition::class)->place($trader->id, $id, 'yes', 1500, (string) Str::uuid());
DB::table('markets')->where('id', $won)->update(['closes_at' => now()->subHour()]);
app(ResolveMarket::class)->resolve($won, 'yes', 'Publication officielle de référence pour le test isolé.');
app(ResolveMarket::class)->resolve($void, 'void', 'Annulation justifiée pour le test isolé de présentation.');
$ticket = DB::table('support_tickets')->insertGetId(['user_id' => $trader->id, 'subject' => 'Suivi de mon paiement et demande détaillée concernant les références de mon portefeuille', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
for ($i = 0; $i < 8; $i++) DB::table('support_messages')->insert(['ticket_id' => $ticket, 'user_id' => $i % 2 ? $admin->id : $trader->id,
    'body' => 'Bonjour, voici les informations nécessaires au suivi de cette demande. '.str_repeat('La référence doit rester consultable sur un petit écran. ', 5).($i === 3 ? str_repeat('RéférenceLongue', 10) : ''), 'created_at' => now()->subMinutes(15 - $i)]);
DB::table('support_tickets')->insert(['user_id' => $trader->id, 'subject' => 'Question résolue à propos de la clôture d’un marché', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
for ($i = 0; $i < 32; $i++) DB::table('platform_notifications')->insert(['user_id' => $trader->id, 'message' => 'Votre opération #'.($i + 1).' a été enregistrée. Consultez votre journal pour retrouver les informations de suivi.', 'read_at' => $i % 2 ? now() : null, 'created_at' => now()->subMinutes($i)]);
foreach (['completed', 'failed', 'awaiting_confirmation', 'initiation_unknown'] as $i => $state) DB::table('payments')->insert([
    'user_id' => $trader->id, 'reference' => (string) Str::uuid(), 'kind' => $i === 3 ? 'withdraw' : 'deposit', 'amount' => 5000 + $i * 1000,
    'currency' => 'XAF', 'country' => 'GAB', 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '[encrypted]', 'phone_encrypted' => Crypt::encryptString('24177000000'),
    'local_state' => $state, 'status' => strtoupper($state), 'created_at' => now()->subHours($i), 'updated_at' => now(),
]);
echo json_encode(['market' => $open, 'longMarket' => $long, 'pendingMarket' => $pending, 'resolvedMarket' => $won, 'ticket' => $ticket], JSON_THROW_ON_ERROR);
