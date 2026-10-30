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
        // No dedicated payout permission, beneficiary approval or MFA flow exists yet.
        return false;
    }

    public function operate(User $user): bool
    {
        return ! $user->suspended && $user->isAdministrator();
    }
}
