<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Créez le compte via /register avant de lui attribuer le rôle administrateur.');

        return 1;
    }
    $user->role = 'admin';
    $user->save();
    $this->info('Rôle administrateur attribué.');
})->purpose('Promouvoir un compte existant en administrateur');

Artisan::command('platform:seed {email}', function () {
    $user = User::where('email', $this->argument('email'))->where('role', 'admin')->first();
    if (! $user) {
        $this->error('Un compte administrateur existant est requis.');

        return 1;
    }
    foreach ([
        ['Sport', 'Démo : les Lions gagneront-ils leur prochain match fictif ?'],
        ['Économie', 'Démo : l’indice fictif panafricain dépassera-t-il 100 points ?'],
        ['Musique', 'Démo : le festival fictif affichera-t-il complet ?'],
        ['Société', 'Démo : le projet solaire fictif sera-t-il inauguré à temps ?'],
        ['Crypto', 'Démo : le token fictif atteindra-t-il son objectif ?'],
        ['Politique', 'Démo : la proposition fictive sera-t-elle adoptée ?'],
    ] as [$category, $title]) {
        if (! DB::table('markets')->where('title', $title)->exists()) {
            DB::table('markets')->insert([
                'creator_id' => $user->id, 'title' => $title, 'category' => $category,
                'description' => 'Scénario entièrement fictif pour tester la plateforme. Oui si le résultat de simulation enregistré par l’administrateur confirme la question ; Non dans le cas contraire. Source : procès-verbal de test fourni à la résolution. En cas de test abandonné, le marché est annulé et toutes les mises sont remboursées.',
                'status' => 'open', 'closes_at' => now()->addDays(7), 'yes_odds' => 200, 'no_odds' => 200, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
    $this->info('Catalogue de démonstration ajouté sans modifier les marchés existants.');
})->purpose('Ajouter six marchés fictifs au catalogue');
