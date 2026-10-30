<?php

namespace App\Modules\Money;

use Illuminate\Support\Facades\DB;

final class Wallets
{
    public static function currency(int $userId): string
    {
        return DB::table('users')->where('id', $userId)->value('active_wallet_currency')
            ?? self::defaultCurrency($userId);
    }

    public static function defaultCurrency(int $userId): string
    {
        return DB::table('users')->where('id', $userId)->value('default_wallet_currency')
            ?? DB::table('payment_wallets')->where('user_id', $userId)->orderBy('id')->value('currency') ?? 'XAF';
    }

    public static function accountCode(int $userId, string $currency): string
    {
        return DB::table('accounts')->where('user_id', $userId)->where('currency', $currency)->value('code')
            ?? (DB::table('accounts')->where('code', "user:$userId")->exists() ? "user:$userId:$currency" : "user:$userId");
    }
}
