<?php

namespace App\Modules\Infrastructure;

use App\Models\User;
use App\Modules\Money\Money;
use App\Modules\Money\RateBook;
use App\Modules\Money\RateSync;
use App\Modules\Payments\Ndsgpay;
use App\Modules\Payments\Recovery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ControlController
{
    private function confirm(Request $request): array
    {
        Gate::authorize('superadmin');
        $data = $request->validate(['current_password' => 'required|string', 'reason' => 'required|string|min:5|max:500']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
        }
        return $data;
    }

    public function index(Request $request, Settings $settings)
    {
        Gate::authorize('superadmin');
        $filters = $request->validate(['state' => ['nullable', Rule::in(['pending', 'completed', 'failed', 'initiation_unknown', 'awaiting_confirmation', 'initiating'])]]);
        $heartbeats = DB::table('infrastructure_heartbeats')->get()->mapWithKeys(fn ($row) => [$row->component => $row->seen_at]);
        return Inertia::render('Infrastructure', [
            'settings' => $settings->current(),
            'catalogue' => collect(config('ndsgpay_operators'))->map(fn ($op, $code) => ['code' => $code,
                'name' => $op['name'], 'country' => config('regions.countries.'.$op['country'].'.name'), 'currency' => $op['currency']])->values(),
            'health' => ['gateway_configured' => app(Ndsgpay::class)->ready(), 'deposits_open' => app(Ndsgpay::class)->ready() && $settings->acceptingDeposits(),
                'queue' => config('queue.default'), 'https' => str_starts_with(config('app.url'), 'https://'), 'debug_off' => ! config('app.debug'),
                'api_key_present' => filled(config('payments.secret_key')), 'webhook_secret_present' => filled(config('payments.webhook_secret')),
                'fx_key_present' => filled(config('fx.key')), 'heartbeats' => $heartbeats,
                'pending_jobs' => DB::table('jobs')->where('queue', 'payments')->count(), 'failed_jobs' => DB::table('failed_jobs')->where('queue', 'payments')->count(),
                'pending_inbox' => DB::table('payment_inbox')->whereNull('processed_at')->count(),
                'pending_outbox' => DB::table('payment_outbox')->whereNull('delivered_at')->count(),
                'anomalies' => DB::table('payment_anomalies')->whereNull('resolved_at')->count()],
            'rates' => collect(array_keys(Money::SCALES))->map(fn ($code) => app(RateBook::class)->view($code)),
            'payments' => DB::table('payments')->when($filters['state'] ?? null, fn ($query, $state) => $query->where('local_state', $state))
                ->select('id', 'reference', 'amount', 'currency', 'correspondent', 'local_state', 'error_code', 'created_at')->latest('id')->paginate(15)->withQueryString(),
            'anomalies' => DB::table('payment_anomalies')->whereNull('resolved_at')->select('id', 'payment_id', 'code', 'created_at')->latest('id')->limit(20)->get(),
            'audit' => DB::table('infrastructure_audit')->leftJoin('users', 'users.id', '=', 'infrastructure_audit.actor_id')
                ->select('infrastructure_audit.id', 'action', 'reason', 'infrastructure_audit.created_at', 'users.name as actor')->latest('infrastructure_audit.id')->limit(30)->get(),
            'filters' => (object) $filters,
        ]);
    }

    public function update(Request $request, Settings $settings)
    {
        $confirmation = $this->confirm($request);
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'app_maintenance' => 'required|boolean', 'deposits_enabled' => 'required|boolean',
            'fx_refresh_enabled' => 'required|boolean', 'maintenance_message' => 'required|string|max:300',
            'min_amount' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,6}\z/D'],
            'max_amount' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,8}\z/D'],
            'operator_codes' => 'present|array|max:24', 'operator_codes.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('ndsgpay_operators')))],
        ]);
        if ((int) $data['min_amount'] > (int) $data['max_amount']) {
            throw ValidationException::withMessages(['max_amount' => 'Le plafond doit être supérieur ou égal au minimum.']);
        }
        if ($data['deposits_enabled'] && ! $data['app_maintenance'] && (! app(Ndsgpay::class)->ready()
            || empty($data['operator_codes']) || config('app.debug') || ! in_array(config('queue.default'), ['database', 'redis']))) {
            throw ValidationException::withMessages(['deposits_enabled' => 'Vérifiez les clés serveur, la queue et au moins un opérateur avant l’ouverture.']);
        }
        foreach (['app_maintenance', 'deposits_enabled', 'fx_refresh_enabled'] as $key) $data[$key] = (bool) $data[$key];
        sort($data['operator_codes']);
        DB::transaction(function () use ($request, $data, $confirmation, $settings) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->isSuperadmin() && ! $actor->suspended, 403);
            $row = DB::table('infrastructure_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ((int) $row->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'La configuration a changé. Rechargez la page avant de réessayer.']);
            }
            $before = $settings->current();
            $after = \Illuminate\Support\Arr::except($data, ['revision']);
            DB::table('infrastructure_settings')->where('id', 1)->update(['settings' => json_encode($after, JSON_THROW_ON_ERROR),
                'revision' => $row->revision + 1, 'updated_at' => now()]);
            DB::table('infrastructure_audit')->insert(['actor_id' => $actor->id, 'action' => 'settings.updated',
                'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR),
                'reason' => $confirmation['reason'], 'created_at' => now()]);
        }, 3);
        return back()->with('success', 'Configuration enregistrée. Les paiements déjà initiés continuent leur vérification.');
    }

    public function operation(Request $request, string $action)
    {
        $confirmation = $this->confirm($request);
        abort_unless(in_array($action, ['reconcile', 'fx'], true), 404);
        // Audit intent before dispatch; no financial creation, shell or arbitrary command.
        DB::table('infrastructure_audit')->insert(['actor_id' => $request->user()->id, 'action' => 'operation.'.$action,
            'reason' => $confirmation['reason'], 'created_at' => now()]);
        if ($action === 'reconcile') {
            $count = app(Recovery::class)->dispatch();
            return back()->with('success', $count.' vérification(s) programmée(s). Aucun nouveau dépôt créé.');
        }
        $ok = app(RateSync::class)->refresh();
        return back()->with('success', $ok ? 'Taux actualisés.' : 'Aucune nouvelle actualisation : délai quotidien ou fournisseur indisponible.');
    }
}
