<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Infrastructure\Settings;
use App\Modules\Ledger\Ledger;
use App\Modules\Money\Money;
use App\Modules\Money\Wallets;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Payments
{
    public const FINAL = ['completed', 'failed'];

    private const DEPOSIT_STATUSES = ['INITIATED', 'SUBMITTED', 'COMPLETED', 'FAILED'];

    private const PAYOUT_STATUSES = ['INITIATED', 'SUBMITTED', 'ACCEPTED', 'ENQUEUED', 'COMPLETED', 'FAILED'];

    public function anomaly(?int $paymentId, string $code, string $scope = ''): void
    {
        $key = hash('sha256', ($paymentId ?? 'inbox').'|'.$code.'|'.$scope);
        if (DB::table('payment_anomalies')->insertOrIgnore(['dedupe_key' => $key, 'payment_id' => $paymentId, 'code' => $code, 'created_at' => now()])) {
            Log::warning('payment_anomaly', ['payment_id' => $paymentId, 'code' => $code]);
        }
    }

    public function create(int $userId, array $data): object
    {
        $user = User::findOrFail($userId);
        abort_unless(app(PaymentPolicy::class)->deposit($user), 403);
        $kind = $data['kind'] ?? null;
        abort_unless(in_array($kind, ['deposit', 'withdraw'], true), 403);
        $payout = $kind === 'withdraw';
        $settings = app(Settings::class);
        abort_unless($payout ? ! $settings->current()['app_maintenance'] : $settings->acceptingDeposits(),
            503, 'Cette opération est temporairement indisponible.');
        abort_unless(app(Ndsgpay::class)->ready(), 503, 'NDSGPay non configuré.');
        abort_if(app()->isProduction() && (config('app.debug') || in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'])), 503, 'Configuration de production incomplète.');
        abort_unless(Str::isUuid($data['reference'] ?? ''), 422);
        $passwordHash = null;
        $payoutOptions = null;
        if ($payout) {
            $currency = Wallets::currency($userId);
            if (($data['currency'] ?? null) !== $currency) {
                throw ValidationException::withMessages(['currency' => 'La devise du retrait doit correspondre au portefeuille sélectionné.']);
            }
            $payoutOptions = app(PayoutOptions::class)->forWallet($userId, $currency);
            abort_unless($payoutOptions, 403);
            $code = $data['correspondent'] ?? null;
            $operator = is_string($code) ? ($payoutOptions['operators'][$code] ?? null) : null;
            if ($operator === null) {
                throw ValidationException::withMessages(['correspondent' => 'Cet opérateur n’est pas autorisé pour la devise du portefeuille.']);
            }
            if (! is_string($data['wallet_token'] ?? null) || ! hash_equals($payoutOptions['token'], $data['wallet_token'])) {
                throw ValidationException::withMessages(['wallet_token' => 'Le portefeuille a changé. Rechargez la page.']);
            }
            $passwordHash = $user->getAuthPassword();
            if (! is_string($data['current_password'] ?? null) || ! Hash::check($data['current_password'], $passwordHash)) {
                throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
            }
            $data = [
                'kind' => $kind, 'reference' => $data['reference'],
                'amount' => Money::minor($data['amount'] ?? null, $currency),
                'currency' => $currency, 'country' => $operator['country'], 'correspondent' => $code,
                'msisdn' => app(Capabilities::class)->normalizePhone($data['msisdn'] ?? null, $operator),
            ];
        } else {
            $data = app(Capabilities::class)->validate($data);
        }
        $fingerprint = hash_hmac('sha256', json_encode([$userId, $data['kind'], $data['amount'], $data['currency'], $data['country'], $data['correspondent'], $data['msisdn']]), config('app.key'));
        try {
            $payment = DB::transaction(function () use ($userId, $data, $fingerprint, $payout, $settings, $payoutOptions, $passwordHash) {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                abort_unless(app(PaymentPolicy::class)->deposit($user), 403);
                DB::table('infrastructure_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
                $currency = Wallets::currency($userId);
                if ($payout) {
                    abort_if($settings->current()['app_maintenance'], 503, 'Les retraits sont temporairement indisponibles.');
                    abort_unless($user->getAuthPassword() === $passwordHash
                        && app(PayoutOptions::class)->forWallet($userId, $currency) === $payoutOptions, 403);
                } else {
                    abort_unless($settings->acceptingDeposits(), 503, 'Les dépôts sont temporairement indisponibles.');
                    $limits = $settings->current();
                    abort_if($data['amount'] < Money::minor($limits['min_amount'], $data['currency']) || $data['amount'] > Money::minor($limits['max_amount'], $data['currency']), 422, 'Limites de dépôt modifiées.');
                    abort_unless(in_array($data['correspondent'], array_column(app(Capabilities::class)->enabled(), 'correspondent'), true), 422, 'Opérateur suspendu.');
                }
                $prior = DB::table('payments')->where('reference', $data['reference'])->first();
                if ($prior) {
                    $matches = $prior->fingerprint && hash_equals($prior->fingerprint, $fingerprint);
                    if (! $prior->fingerprint) { // Legacy encrypted operation, same strict identity.
                        $matches = (int) $prior->user_id === $userId && $prior->kind === $data['kind']
                            && (int) $prior->amount === $data['amount'] && $prior->currency === $data['currency']
                            && $prior->correspondent === $data['correspondent'] && Crypt::decryptString($prior->phone_encrypted) === $data['msisdn'];
                    }
                    if (! $matches) {
                        throw ValidationException::withMessages(['reference' => 'Référence déjà utilisée avec des paramètres différents.']);
                    }

                    return null;
                }
                // One active operation of each kind per user, even with a new UUID.
                if (DB::table('payments')->where('user_id', $userId)->where('kind', $data['kind'])->whereNotIn('local_state', self::FINAL)->exists()) {
                    throw ValidationException::withMessages(['reference' => $payout
                        ? 'Un retrait attend déjà sa confirmation.' : 'Un dépôt attend déjà sa confirmation. Ne relancez pas le paiement.']);
                }
                if ($payout) {
                    $recent = DB::table('payments')->where('user_id', $userId)->where('kind', 'withdraw')
                        ->where('currency', $currency)->where('local_state', 'completed')
                        ->where('confirmed_at', '>=', now()->subDay())->sum('amount');
                    if ($data['amount'] < $payoutOptions['min_amount'] || $data['amount'] > $payoutOptions['max_amount']
                        || $recent > $payoutOptions['daily_limit'] - $data['amount']) {
                        throw ValidationException::withMessages(['amount' => 'Plafond de retrait par opération ou sur 24 heures dépassé.']);
                    }
                }
                if (! DB::table('payment_wallets')->where('user_id', $userId)->exists()) {
                    DB::table('payment_wallets')->insert(['user_id' => $userId, 'currency' => 'XAF', 'balance' => 0]);
                }
                $wallet = DB::table('payment_wallets')->where('user_id', $userId)->where('currency', $currency)->lockForUpdate()->firstOrFail();
                if ($wallet->currency !== $data['currency'] || ($payout && (int) $wallet->id !== $payoutOptions['wallet_id'])) {
                    throw ValidationException::withMessages(['correspondent' => 'Cet opérateur ne correspond pas à la devise du portefeuille sélectionné.']);
                }
                $record = $data;
                unset($record['msisdn']);
                $id = DB::table('payments')->insertGetId([
                    ...$record, 'user_id' => $userId, 'provider' => 'ndsgpay', 'merchant_id' => Ndsgpay::merchantScope(),
                    'business_type' => $payout ? 'wallet_payout' : 'wallet_topup', 'business_id' => $data['reference'], 'fingerprint' => $fingerprint,
                    'phone_encrypted' => Crypt::encryptString($data['msisdn']), 'msisdn' => '[encrypted]',
                    'correlation_id' => (string) Str::uuid(), 'active_key' => ($payout ? 'payout:' : 'topup:').$userId,
                    'local_state' => 'initiating', 'status' => 'CREATING', 'initiated_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($payout) {
                    app(Ledger::class)->transfer($userId, (string) Str::uuid(), 'Retrait NDSGPay',
                        $data['amount'], false, 'payment:'.$id, $id, 'reserve', $currency);
                }

                return DB::table('payments')->find($id);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $prior = DB::table('payments')->where('reference', $data['reference'])->first();
            if (! $prior || $prior->user_id != $userId || ! $prior->fingerprint || ! hash_equals($prior->fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['reference' => 'Référence ou opération active déjà utilisée.']);
            }

            return $prior;
        }
        if (! $payment) {
            return DB::table('payments')->where('reference', $data['reference'])->firstOrFail();
        }
        // Only the process that inserted the operation calls the provider. There is no queued POST retry.
        try {
            $response = app(Ndsgpay::class)->request('POST', $payout ? '/pay/payouts' : '/pay/deposits', [
                'amount' => Money::decimal((int) $payment->amount, $payment->currency), 'currency' => $payment->currency,
                'correspondent' => $payment->correspondent, 'msisdn' => $data['msisdn'],
                'description' => Ndsgpay::description('PredicAfrica'),
            ]);
            $providerId = $response[$payout ? 'payoutId' : 'depositId'] ?? null;
            if (! Ndsgpay::validId($providerId)) {
                throw new GatewayFailure('invalid_response');
            }
            $validStatus = in_array($response['status'] ?? null, $payout ? self::PAYOUT_STATUSES : self::DEPOSIT_STATUSES, true);
            DB::table('payments')->where('id', $payment->id)->whereNull('provider_id')->whereNotIn('local_state', self::FINAL)->update($validStatus ? [
                'provider_id' => $providerId, 'provider_status' => $response['status'],
                'local_state' => 'pending', 'status' => 'INITIATED', 'next_check_at' => now(), 'updated_at' => now(),
            ] : [
                'provider_id' => $providerId, 'local_state' => 'initiation_unknown', 'status' => 'UNKNOWN',
                'error_code' => 'invalid_response', 'next_check_at' => now(), 'updated_at' => now(),
            ]);
            if (! $validStatus) {
                $this->anomaly($payment->id, 'invalid_response');
            }
        } catch (GatewayFailure|UniqueConstraintViolationException $exception) {
            // Provider-side 400/401/403 are classified, but never silently retried or used to free funds.
            // Locally refused requests did not leave our process and are safe to close.
            $unknown = ! ($exception instanceof GatewayFailure) || $exception->sent;
            $reason = $exception instanceof GatewayFailure ? $exception->reason : 'duplicate_provider_id';
            DB::transaction(function () use ($payment, $unknown, $reason, $payout) {
                DB::table('users')->where('id', $payment->user_id)->lockForUpdate()->firstOrFail();
                $updated = DB::table('payments')->where('id', $payment->id)->whereNull('provider_id')->whereNotIn('local_state', self::FINAL)->update([
                    'local_state' => $unknown ? 'initiation_unknown' : 'failed', 'status' => $unknown ? 'UNKNOWN' : 'FAILED',
                    'active_key' => $unknown ? $payment->active_key : null, 'error_code' => $reason, 'updated_at' => now(),
                ]);
                if ($updated && $payout && ! $unknown) {
                    $this->move($payment, 'release', (int) $payment->amount);
                }
                $this->anomaly($payment->id, $reason);
            }, 3);
        }

        // A database/process crash after POST leaves 'initiating'. Recovery quarantines it; never resends.
        return DB::table('payments')->find($payment->id);
    }

    public function refresh(object $snapshot, bool $force = false): bool
    {
        $token = (string) Str::uuid();
        $payment = DB::transaction(function () use ($snapshot, $token, $force) {
            $payment = DB::table('payments')->where('id', $snapshot->id)->lockForUpdate()->firstOrFail();
            if (! $payment->provider_id || (! $force && $payment->confirmed_at && in_array($payment->local_state, self::FINAL))
                || ($payment->check_lease_until && now()->lt($payment->check_lease_until))
                || ($payment->next_check_at && now()->lt($payment->next_check_at))) {
                return null;
            }
            DB::table('payments')->where('id', $payment->id)->update(['check_token' => $token, 'check_lease_until' => now()->addSeconds(30)]);

            return $payment;
        }, 3);
        if (! $payment) {
            return false;
        }
        try {
            $this->applyVerified($payment->id, $this->verifiedStatus($payment), $token);

            return true;
        } catch (GatewayFailure $exception) {
            $attempts = min(20, (int) $payment->check_attempts + 1);
            $delay = max($exception->retryAfter, min(21600, 15 * (2 ** min(10, $attempts))) + random_int(0, 15));
            DB::table('payments')->where('id', $payment->id)->where('check_token', $token)->update([
                'check_token' => null, 'check_lease_until' => null, 'last_checked_at' => now(), 'check_attempts' => $attempts,
                'next_check_at' => now()->addSeconds($delay), 'error_code' => $exception->reason,
            ]);
            if ($exception->reason !== 'local_rate_limit') {
                $this->anomaly($payment->id, $exception->reason);
            }

            return false;
        }
    }

    private function verifiedStatus(object $payment): string
    {
        if (($payment->merchant_id ?: null) !== Ndsgpay::merchantScope()) {
            throw new GatewayFailure('merchant_mismatch', false);
        }
        if (! Ndsgpay::validId($payment->provider_id)) {
            throw new GatewayFailure('invalid_provider_id', false);
        }
        $deposit = $payment->kind === 'deposit';
        $response = app(Ndsgpay::class)->request('GET', ($deposit ? '/deposits/' : '/payouts/').$payment->provider_id);
        $details = $response[$deposit ? 'deposit' : 'payout'] ?? null;
        if (! is_array($details) || ($details[$deposit ? 'depositId' : 'payoutId'] ?? null) !== $payment->provider_id
            || ! $this->amountMatches($details['amount'] ?? null, (int) $payment->amount, $payment->currency)
            || ($details['currency'] ?? null) !== $payment->currency) {
            throw new GatewayFailure('transaction_mismatch');
        }
        $statuses = $deposit ? self::DEPOSIT_STATUSES : self::PAYOUT_STATUSES;
        if (! in_array($details['status'] ?? null, $statuses, true)) {
            throw new GatewayFailure('unknown_status');
        }
        if ($payment->business_type === 'wallet_payout' && in_array($details['status'], ['COMPLETED', 'FAILED'], true)) {
            try {
                $phone = Crypt::decryptString($payment->phone_encrypted);
            } catch (DecryptException) {
                throw new GatewayFailure('beneficiary_unavailable');
            }
            if (($details['correspondent'] ?? null) !== $payment->correspondent || ($details['msisdn'] ?? null) !== $phone) {
                throw new GatewayFailure('payout_beneficiary_mismatch');
            }
        }

        return $details['status'];
    }

    public function acceptLateDeposit(string $reference, int $actorId, string $case): void
    {
        abort_unless(app(PaymentPolicy::class)->operate(User::findOrFail($actorId)), 403);
        abort_unless(preg_match('/\A[A-Za-z0-9_-]{3,80}\z/', $case), 422);
        $token = (string) Str::uuid();
        $payment = DB::transaction(function () use ($reference, $token) {
            $payment = DB::table('payments')->where('reference', $reference)->lockForUpdate()->firstOrFail();
            abort_unless($payment->kind === 'deposit' && $payment->local_state === 'failed' && $payment->provider_id, 409);
            abort_if($payment->check_lease_until && now()->lt($payment->check_lease_until), 409);
            DB::table('payments')->where('id', $payment->id)->update(['check_token' => $token, 'check_lease_until' => now()->addSeconds(30)]);

            return $payment;
        }, 3);
        // An operator resolution still requires the same authenticated GET checks.
        $status = $this->verifiedStatus($payment);
        abort_unless($status === 'COMPLETED', 409, 'Succès prestataire non confirmé.');
        $this->applyVerified($payment->id, $status, $token, ['actor_id' => $actorId, 'case_reference' => $case]);
    }

    private function amountMatches(mixed $amount, int $expected, string $currency): bool
    {
        try {
            return Money::minor($amount, $currency) === $expected;
        } catch (\Throwable) {
            return false;
        }
    }

    private function applyVerified(int $id, string $status, string $token, ?array $resolution = null): void
    {
        $snapshot = DB::table('payments')->find($id);
        DB::transaction(function () use ($snapshot, $id, $status, $token, $resolution) {
            DB::table('users')->where('id', $snapshot->user_id)->lockForUpdate()->firstOrFail();
            $payment = DB::table('payments')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($payment->check_token !== $token) {
                return; // A stale worker lost its lease; no financial consequence.
            }
            $updates = ['check_token' => null, 'check_lease_until' => null, 'last_checked_at' => now(), 'check_attempts' => 0,
                'next_check_at' => now()->addSeconds(60), 'error_code' => null, 'updated_at' => now()];
            $lateDeposit = $resolution && $payment->kind === 'deposit' && $payment->local_state === 'failed' && $status === 'COMPLETED';
            if (in_array($payment->local_state, self::FINAL) && ! $lateDeposit) {
                if (in_array($status, ['COMPLETED', 'FAILED']) && strtoupper($payment->local_state) !== $status) {
                    $this->anomaly($id, 'final_contradiction');
                } elseif (strtoupper($payment->local_state) === $status) {
                    $updates['confirmed_at'] = now();
                }
                DB::table('payments')->where('id', $id)->update($updates);

                return;
            }
            $final = in_array($status, ['COMPLETED', 'FAILED']);
            $updates += ['provider_status' => $status, 'status' => $status,
                'local_state' => $final ? strtolower($status) : 'pending'];
            if ($final) {
                $updates['confirmed_at'] = now();
                $updates['active_key'] = null;
                $updates['next_check_at'] = null;
                if ($payment->kind === 'deposit' && $status === 'COMPLETED') {
                    $this->move($payment, 'credit', (int) $payment->amount);
                }
                if ($payment->kind === 'withdraw' && $status === 'FAILED') {
                    $this->move($payment, 'release', (int) $payment->amount);
                }
                if ($lateDeposit) {
                    DB::table('payment_audit')->insert(['payment_id' => $id, ...$resolution, 'action' => 'accept_late_deposit', 'created_at' => now()]);
                }
                DB::table('payment_outbox')->insertOrIgnore(['payment_id' => $id, 'event' => $lateDeposit ? 'LATE_COMPLETED' : $status, 'created_at' => now()]);
            }
            DB::table('payments')->where('id', $id)->update($updates);
        }, 3);
    }

    private function move(object $payment, string $effect, int $amount): void
    {
        $wallet = DB::table('payment_wallets')->where('user_id', $payment->user_id)->where('currency', $payment->currency)->firstOrFail();
        if ($wallet->currency !== $payment->currency || $amount <= 0 || $wallet->balance > PHP_INT_MAX - $amount) {
            throw new \LogicException('Invariant du portefeuille violé.');
        }
        if ($effect === 'release' && ! DB::table('payment_movements')->where('payment_id', $payment->id)
            ->where('user_id', $payment->user_id)->where('currency', $payment->currency)
            ->where('effect', 'reserve')->where('amount', -$amount)->exists()) {
            throw new \LogicException('Réservation de retrait absente ou incohérente.');
        }
        $prior = DB::table('payment_movements')->where('payment_id', $payment->id)->where('effect', $effect)->first();
        if ($prior) {
            if ((int) $prior->amount !== $amount) {
                throw new \LogicException('Mouvement financier incohérent.');
            }

            return;
        }
        app(Ledger::class)->transfer(
            $payment->user_id, (string) Str::uuid(), $effect === 'release' ? 'Restitution retrait' : 'Dépôt NDSGPay',
            $amount, true, 'payment:'.$payment->id, $payment->id, $effect, $payment->currency,
        );

    }
}
