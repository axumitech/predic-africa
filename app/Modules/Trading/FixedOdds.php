<?php

namespace App\Modules\Trading;

use Illuminate\Validation\ValidationException;

final class FixedOdds
{
    public static function payout(int $stake, int $odds): int
    {
        if ($stake < 100 || $stake > 1000000 || $odds < 101 || $odds > 1000) {
            throw ValidationException::withMessages(['amount' => 'Mise ou cote enregistrée invalide. Un rapprochement est nécessaire.']);
        }

        return intdiv($stake * $odds, 100);
    }
}
