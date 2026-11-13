<?php

namespace App\Console\Commands;

use App\Modules\Markets\MarketState;
use App\Modules\Settlement\CafMatchReport;
use App\Modules\Settlement\OfficialResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateCafMarket extends Command
{
    protected $signature = 'markets:caf-check {event} {--activate : Raccorder le marché fermé après validation}';

    protected $description = 'Vérifier un rapport CAF et son contrat avant raccordement';

    public function handle(OfficialResult $official): int
    {
        $event = $this->argument('event');
        $fixture = config('caf_qualifiers')[$event] ?? null;
        if ($fixture === null) {
            $this->error('Rencontre CAF inconnue.');

            return self::FAILURE;
        }

        $kickoff = CarbonImmutable::parse($fixture['kickoff']);
        $eligibleAt = $kickoff->addMinutes(150);
        $rule = json_encode([
            'providers' => ['caf_match_report'], 'event' => $event, 'condition' => CafMatchReport::CONDITION,
            'yes_value' => 'home_win', 'no_value' => 'no_home_win', 'on_cancel' => 'void', 'on_tie' => 'no',
        ], JSON_THROW_ON_ERROR);
        [$first, $second] = $fixture['market_teams'];
        $title = "$first – $second · Victoire de la première équipe dans le temps réglementaire ?";
        $description = "Oui si $first gagne à l’issue du temps réglementaire, arrêts de jeu compris. Non en cas de nul ou de victoire de $second. Si le match est reporté ou annulé, annuler le marché.";
        $query = DB::table('markets')->where('pipeline_source', $fixture['source']);
        $matches = fn ($market) => $market && $market->title === $title
            && str_starts_with($market->description, $description."\n\nAnnonce officielle\n")
            && CarbonImmutable::parse($market->closes_at, 'UTC')->lte($kickoff);
        $market = $query->first();
        if (! $matches($market) || now()->lt($eligibleAt)) {
            $this->error('Contrat incompatible ou période d’observation en cours.');

            return self::FAILURE;
        }

        [$outcome, $evidence] = $official->check((object) ['resolution_rule' => $rule, 'event_ends_at' => $eligibleAt]);
        if (! in_array($outcome, ['yes', 'no'], true)) {
            $this->error('Rapport final CAF absent.');

            return self::FAILURE;
        }
        $this->line(json_encode(['market_id' => $market->id, 'outcome' => $outcome, 'evidence' => $evidence], JSON_THROW_ON_ERROR));

        if ($this->option('activate')) {
            DB::transaction(function () use ($query, $market, $matches, $rule, $eligibleAt, $evidence) {
                $locked = $query->where('id', $market->id)->lockForUpdate()->first();
                abort_unless($matches($locked) && $locked->status === 'closed' && $locked->result === null
                    && $locked->resolution_rule === null, 422, 'Marché incompatible ou déjà raccordé.');
                MarketState::transition($locked, 'pending_resolution', 'Raccordement du rapport officiel CAF.', [
                    'event_ends_at' => $eligibleAt, 'processing_due_at' => now(), 'resolution_rule' => $rule,
                ], evidence: $evidence);
            }, 3);
        }

        return self::SUCCESS;
    }
}
