<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestMarketsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Les marchés fictifs sont réservés aux environnements local et testing.');
        }
        $creator = DB::table('users')->where('suspended', false)->whereIn('role', ['trader', 'admin', 'superadmin'])
            ->orderByRaw("CASE WHEN role IN ('admin', 'superadmin') THEN 0 ELSE 1 END")->orderBy('id')->first();
        if (! $creator) {
            throw new \RuntimeException('Créez un compte avant d’ajouter les marchés de test.');
        }
        // All questions and results below are fictional. No positions, payments,
        // wallet balances or participant counts are invented by this seeder.
        $scenarios = [
            ['sport', 'Sport', 'Le FC Horizon remportera-t-il le match de démonstration ?', 'open', 48, 185, 210, null],
            ['politique', 'Politique', 'Le conseil fictif adoptera-t-il la proposition Alpha ?', 'open', 96, 160, 240, null],
            ['musique', 'Musique', 'L’artiste fictif Nova annoncera-t-il un nouvel album ?', 'open', 168, 220, 175, null],
            ['economie', 'Économie', 'L’indice fictif PA-100 dépassera-t-il les 1 000 points ?', 'open', 240, 195, 195, null],
            ['crypto', 'Crypto', 'Le jeton fictif TEST clôturera-t-il au-dessus de 10 unités ?', 'open', 72, 250, 150, null],
            ['societe', 'Société', 'La ville fictive Lumière ouvrira-t-elle son nouveau parc ?', 'open', 120, 145, 280, null],
            ['closing-soon', 'Sport', 'Le match fictif Express comptera-t-il au moins trois buts ?', 'open', 2, 200, 190, null],
            ['long-title', 'Économie', 'Le programme de démonstration des entreprises locales atteindra-t-il son objectif fictif de cent participants avant la clôture ?', 'open', 336, 175, 225, null],
            ['closed', 'Société', 'Le projet fictif Delta sera-t-il livré dans les délais ?', 'open', -2, 180, 220, null],
            ['pending', 'Politique', 'La proposition fictive Beta sera-t-elle retenue par le comité ?', 'pending', 144, 205, 185, null],
            ['resolved', 'Musique', 'Le concert fictif Étoile affichera-t-il complet ?', 'resolved', -24, 170, 230, 'yes'],
            ['void', 'Crypto', 'Le lancement fictif du jeton DEMO aura-t-il lieu ?', 'void', -48, 200, 190, 'void'],
        ];
        $created = DB::transaction(function () use ($creator, $scenarios) {
            $count = 0;
            foreach ($scenarios as [$key, $category, $title, $status, $hours, $yes, $no, $result]) {
                $source = 'local-test-market:v1:'.$key;
                if (DB::table('markets')->where('pipeline_source', $source)->exists()) continue;
                DB::table('markets')->insert([
                    'creator_id' => $creator->id, 'pipeline_source' => $source, 'title' => '[TEST] '.$title,
                    'description' => "SCÉNARIO FICTIF — TEST LOCAL UNIQUEMENT.\n\nCette question ne décrit aucun événement réel. Utilisez-la pour tester l’affichage, les filtres et le parcours de prise de position.\n\nRésolution : un administrateur renseigne manuellement le résultat du scénario de démonstration. « Oui » si l’objectif fictif est atteint, « Non » sinon. Une annulation rembourse les mises. Les prises de position restent des écritures dans le portefeuille utilisé.",
                    'category' => $category, 'status' => $status, 'closes_at' => now()->addHours($hours),
                    'yes_odds' => $yes, 'no_odds' => $no, 'result' => $result,
                    'resolution_source' => $result ? 'Résultat fictif défini pour les tests locaux, sans événement ni source réelle.' : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $count++;
            }
            return $count;
        });
        $this->command?->info("$created marché(s) fictif(s) ajouté(s). Les scénarios déjà présents sont conservés.");
    }
}
