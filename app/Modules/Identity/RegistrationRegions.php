<?php

namespace App\Modules\Identity;

class RegistrationRegions
{
    public function currenciesFor(mixed $country): array
    {
        $countries = config('regions.countries');

        return is_string($country) ? ($countries[$country]['currencies'] ?? []) : [];
    }

    public function options(): array
    {
        $options = [];
        foreach (config('regions.countries') as $code => $country) {
            $options[] = ['code' => $code, 'name' => $country['name'],
                'currencies' => array_map(fn ($currency) => ['code' => $currency, 'name' => config('regions.currencies')[$currency]], $country['currencies'])];
        }

        return $options;
    }
}
