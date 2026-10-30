<?php

namespace App\Modules\Identity;

use Illuminate\Validation\ValidationException;

class RegistrationPhone
{
    public function normalize(mixed $country, mixed $input): string
    {
        $plan = is_string($country) ? config('regions.phones.'.$country) : null;
        if (! is_array($plan) || ! is_string($input) || strlen($input) > 32
            || ! preg_match('/\A\+?[0-9 ()-]+\z/D', $input)) {
            $this->invalid();
        }
        $digits = str_replace(['+', ' ', '(', ')', '-'], '', $input);
        $prefix = $plan['calling_code'];
        $length = $plan['length'];
        if (str_starts_with($input, '+') || strlen($digits) === strlen($prefix) + $length) {
            if (! str_starts_with($digits, $prefix)) $this->invalid();
            $digits = substr($digits, strlen($prefix));
        } elseif (isset($plan['trunk']) && strlen($digits) === $length + 1 && str_starts_with($digits, $plan['trunk'])) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== $length || ! ctype_digit($digits) || preg_match('/\A0+\z/D', $digits)) {
            $this->invalid();
        }

        return '+'.$prefix.$digits;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['phone' => 'Renseignez un numéro valide correspondant au pays sélectionné.']);
    }
}
