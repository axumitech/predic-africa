<?php

namespace App\Modules\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class Money
{
    // ISO 4217 minor units. XAF history remains in francs, without rescaling.
    public const SCALES = ['XAF' => 0, 'XOF' => 0, 'CDF' => 2, 'KES' => 2, 'RWF' => 0, 'UGX' => 0, 'ZMW' => 2, 'SLE' => 2];

    public static function scale(string $currency): int
    {
        return self::SCALES[$currency] ?? throw new \InvalidArgumentException('Devise non prise en charge.');
    }

    public static function minor(mixed $amount, string $currency): int
    {
        try {
            if ((! is_string($amount) && ! is_int($amount)) || ! preg_match('/\A[0-9]{1,19}(?:\.[0-9]{1,12})?\z/D', (string) $amount)) {
                throw new \InvalidArgumentException;
            }

            return BigDecimal::of($amount)->withPointMovedRight(self::scale($currency))->toScale(0)->toInt();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Montant invalide : utilisez un nombre positif avec la précision de votre devise.']);
        }
    }

    public static function decimal(int $minor, string $currency): string
    {
        return (string) BigDecimal::of($minor)->withPointMovedLeft(self::scale($currency))->toScale(self::scale($currency));
    }

    public static function convert(int $minor, string $from, string $to, string $rate, RoundingMode $rounding = RoundingMode::Down): int
    {
        return BigDecimal::of($minor)->withPointMovedLeft(self::scale($from))->multipliedBy($rate)
            ->withPointMovedRight(self::scale($to))->toScale(0, $rounding)->toInt();
    }

    public static function toBase(int $minor, string $currency, string $rate): int
    {
        return BigDecimal::of($minor)->withPointMovedLeft(self::scale($currency))->dividedBy($rate, 0, RoundingMode::Down)->toInt();
    }

    public static function payout(int $minor, int $odds): int
    {
        if ($minor <= 0 || $odds < 101 || $odds > 1000) {
            throw ValidationException::withMessages(['amount' => 'Montant ou cote invalide.']);
        }

        return BigInteger::of($minor)->multipliedBy($odds)->quotient(100)->toInt();
    }
}
