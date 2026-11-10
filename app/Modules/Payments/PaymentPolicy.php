<?php

namespace App\Modules\Payments;

use App\Models\User;

class PaymentPolicy
{
    public function deposit(User $user): bool
    {
        return ! $user->suspended && in_array($user->role, ['trader', 'admin', 'superadmin'], true);
    }

    public function view(User $user, object $payment): bool
    {
        return $this->deposit($user) && (int) $payment->user_id === $user->id;
    }

    public function payout(User $user): bool
    {
        return $this->deposit($user)
            && app(PayoutBeneficiaries::class)->find($user->id, \App\Modules\Money\Wallets::currency($user->id)) !== null;
    }

    public function operate(User $user): bool
    {
        return ! $user->suspended && $user->isAdministrator();
    }
}
