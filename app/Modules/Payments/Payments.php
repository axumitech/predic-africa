<?php

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Payments
{
    public const FINAL = ['completed', 'failed'];

    public function anomaly(?int $paymentId, string $code, string $scope = ''): void
    {
        $key = hash('sha256', ($paymentId ?? 'inbox').'|'.$code.'|'.$scope);
        if (DB::table('payment_anomalies')->insertOrIgnore(['dedupe_key' => $key, 'payment_id' => $paymentId, 'code' => $code, 'created_at' => now()])) {
            Log::warning('payment_anomaly', ['payment_id' => $paymentId, 'code' => $code]);
        }
    }

    public function create(int $userId, array $data): object
    {
        abort_unless(app(PaymentPolicy::class)->deposit(User::findOrFail($userId)), 403);
        abort_if(($data['kind'] ?? '') !== 'deposit', 403, 'Payouts désactivés.');
        abort_unless(app(Ndsgpay::class)->ready(), 503, 'NDSGPay non configuré.');
        abort_if(app()->isProduction() && (config('app.debug') || in_array(config('queue.default'), ['sync', 'null', 'deferred', 'background'])), 503, 'Configuration de production incomplète.');
        abort_unless(Str::isUuid($data['reference'] ?? ''), 422);
        $data = app(Capabilities::class)->validate($data);
        $fingerprint = hash_hmac('sha256', json_encode([$userId, $data['kind'], $data['amount'], $data['currency'], $data['country'], $data['correspondent'], $data['msisdn']]), config('app.key'));
        try {
            $payment = DB::transaction(function () use ($userId, $data, $fingerprint) {
                $user = DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
                abort_if($user->suspended, 403);
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
                // Conservative wallet rule: one active topup per user, even with a new UUID.
                if (DB::table('payments')->where('user_id', $userId)->where('kind', 'deposit')->whereNotIn('local_state', self::FINAL)->exists()) {
                    throw ValidationException::withMessages(['reference' => 'Un dépôt attend déjà sa confirmation. Ne relancez pas le paiement.']);
                }
                DB::table('payment_wallets')->insertOrIgnore(['user_id' => $userId, 'currency' => 'XAF', 'balance' => 0]);
                $wallet = DB::table('payment_wallets')->where('user_id', $userId)->lockForUpdate()->firstOrFail();
                if ($wallet->currency !== $data['currency'] || ($user->country !== null && $user->country !== $data['country'])) {
                    throw ValidationException::withMessages(['correspondent' => 'Cet opérateur ne correspond pas au pays et à la devise de votre portefeuille.']);
                }
                $record = $data;
                unset($record['msisdn']);
                $id = DB::table('payments')->insertGetId([
                    ...$record, 'user_id' => $userId, 'provider' => 'ndsgpay', 'merchant_id' => Ndsgpay::merchantScope(),
                    'business_type' => 'wallet_topup', 'business_id' => $data['reference'], 'fingerprint' => $fingerprint,
                    'phone_encrypted' => Crypt::encryptString($data['msisdn']), 'msisdn' => '[encrypted]',
                    'correlation_id' => (string) Str::uuid(), 'active_key' => 'topup:'.$userId,
                    'local_state' => 'initiating', 'status' => 'CREATING', 'initiated_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return DB::table('payments')->find($id);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $prior = DB::table('payments')->where('reference', $data['reference'])->first();
            if (! $prior || $prior->user_id != $userId || ! $prior->fingerprint || ! hash_equals($prior->fingerprint, $fingerprint)) {
                throw ValidationException::withMessages(['reference' => 'Référence ou dépôt actif déjà utilisé.']);
            }

            return $prior;
        }
        if (! $payment) {
            return DB::table('payments')->where('reference', $data['reference'])->firstOrFail();
        }
        // Only the process that inserted the operation calls the provider. There is no queued POST retry.
        try {
            $response = app(Ndsgpay::class)->request('POST', '/pay/deposits', [
                'amount' => Money::decimal((int) $payment->amount, $payment->currency), 'currency' => $payment->currency,
                'correspondent' => $payment->correspondent, 'msisdn' => $data['msisdn'],
                'description' => Ndsgpay::description('PredicAfrica'),
            ]);
            if (! Ndsgpay::validId($response['depositId'] ?? null)) {
                throw new GatewayFailure('invalid_response');
            }
            // Retain a usable provider ID even when another creation field is malformed.
            DB::table('payments')->where('id', $payment->id)->update(['provider_id' => $response['depositId']]);
            if (! in_array($response['status'] ?? null, ['INITIATED', 'SUBMITTED', 'COMPLETED', 'FAILED'], true)) {
                throw new GatewayFailure('invalid_response');
            }
            DB::table('payments')->where('id', $payment->id)->update([
                'provider_id' => $response['depositId'], 'provider_status' => $response['status'],
                'local_state' => 'pending', 'status' => 'INITIATED', 'next_check_at' => now(), 'updated_at' => now(),
            ]);
        } catch (GatewayFailure $exception) {
            // Provider-side 400/401/403 are classified, but never silently retried or used to free funds.
            // Locally refused requests did not leave our process and are safe to close.
            $unknown = $exception->sent;
            DB::table('payments')->where('id', $payment->id)->update([
                'local_state' => $unknown ? 'initiation_unknown' : 'failed', 'status' => $unknown ? 'UNKNOWN' : 'FAILED',
                'active_key' => $unknown ? $payment->active_key : null, 'error_code' => $exception->reason, 'updated_at' => now(),
            ]);
            $this->anomaly($payment->id, $exception->reason);
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
        $statuses = $deposit ? ['INITIATED', 'SUBMITTED', 'COMPLETED', 'FAILED'] : ['INITIATED', 'SUBMITTED', 'ACCEPTED', 'ENQUEUED', 'COMPLETED', 'FAILED'];
        if (! in_array($details['status'] ?? null, $statuses, true)) {
            throw new GatewayFailure('unknown_status');
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
        $wallet = DB::table('payment_wallets')->where('user_id', $payment->user_id)->firstOrFail();
        if ($wallet->currency !== $payment->currency || $amount <= 0 || $wallet->balance > PHP_INT_MAX - $amount) {
            throw new \LogicException('Invariant du portefeuille violé.');
        }
        if ($effect === 'release' && ! DB::table('payment_movements')->where('payment_id', $payment->id)
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
