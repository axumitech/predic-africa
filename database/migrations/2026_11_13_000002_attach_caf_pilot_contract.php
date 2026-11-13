<?php

use App\Modules\Markets\MarketState;
use App\Modules\Settlement\CafMatchReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $market = DB::table('markets')
                ->where('pipeline_source', 'africa:caf-2027-3-rd-congo-sierra-leone')
                ->where('closes_at', '2026-11-12 15:55:00')
                ->where('status', 'closed')->whereNull('result')->whereNull('resolution_rule')
                ->lockForUpdate()->first();

            if (! $market || $market->title !== 'RD Congo – Sierra Leone · Victoire de la première équipe dans le temps réglementaire ?'
                || ! str_starts_with($market->description, 'Oui si RD Congo gagne à l’issue du temps réglementaire, arrêts de jeu compris. Non en cas de nul ou de victoire de Sierra Leone. Si le match est reporté ou annulé, annuler le marché.')) {
                return;
            }

            MarketState::transition($market, 'pending_resolution', 'Raccordement du rapport officiel CAF au marché pilote.', [
                'event_ends_at' => '2026-11-12 18:30:00',
                'processing_due_at' => now(),
                'resolution_rule' => json_encode([
                    'providers' => ['caf_match_report'], 'event' => CafMatchReport::EVENT,
                    'condition' => CafMatchReport::CONDITION, 'yes_value' => 'home_win', 'no_value' => 'no_home_win',
                    'on_cancel' => 'void', 'on_tie' => 'no',
                ], JSON_THROW_ON_ERROR),
            ], evidence: ['url' => CafMatchReport::URL]);
        }, 3);
    }

    public function down(): void
    {
        // A published resolution and its settlement history must survive rollback.
    }
};
