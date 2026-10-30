<?php

namespace App\Http\Controllers;

use App\Modules\Predictions\Infrastructure\PipelineClient;
use App\Modules\Predictions\Infrastructure\PipelineUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function page(Request $request, string $page = 'markets', ?int $id = null)
    {
        $user = $request->user();
        $data = [];
        if ($page === 'home') {
            $data['stats'] = ['volume' => (int) DB::table('positions')->sum('stake'), 'open_markets' => DB::table('markets')->where('status', 'open')->where('closes_at', '>', now())->count()];
            $data['markets'] = DB::table('markets')->where('status', 'open')->where('closes_at', '>', now())->orderBy('closes_at')->paginate(3);
        }
        if ($page === 'dashboard' && $user->role === 'admin') {
            return redirect('/admin');
        }
        if ($page === 'dashboard') {
            $positions = DB::table('positions')->where('user_id', $user->id);
            $data['stats'] = [
                'active' => (clone $positions)->where('status', 'open')->count(),
                'engaged' => (int) (clone $positions)->where('status', 'open')->sum(DB::raw('COALESCE(wallet_stake, stake)')),
                'potential' => (int) (clone $positions)->where('status', 'open')->sum(DB::raw('COALESCE(wallet_payout, payout)')),
                'won' => (clone $positions)->where('status', 'won')->count(),
                'pending' => DB::table('markets')->where('creator_id', $user->id)->where('status', 'pending')->count(),
            ];
            $data['markets'] = DB::table('markets')->where('status', 'open')->where('closes_at', '>', now())->orderBy('closes_at')->paginate(6);
            $data['recentActivity'] = DB::table('ledger_transactions')->where('user_id', $user->id)->select('id', 'kind', 'amount', 'currency', 'created_at')->orderByDesc('id')->limit(5)->get();
        }
        if (str_starts_with($page, 'admin')) {
            Gate::authorize('admin');
        }
        if (in_array($page, ['markets', 'admin-markets', 'creator'])) {
            $query = DB::table('markets')->select('markets.*')
                ->selectSub(DB::table('positions')->selectRaw('COALESCE(SUM(stake), 0)')->whereColumn('market_id', 'markets.id'), 'volume')
                ->selectSub(DB::table('positions')->selectRaw('COUNT(DISTINCT user_id)')->whereColumn('market_id', 'markets.id'), 'participants')
                ->selectSub(DB::table('users')->select('name')->whereColumn('users.id', 'markets.creator_id'), 'creator_name');
            if ($page === 'markets') {
                $query->whereIn('status', ['open', 'resolved', 'void']);
            } elseif ($page === 'creator') {
                $query->where('creator_id', $user->id);
            }
            $filters = $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:30', 'status' => 'nullable|string|max:30', 'sort' => 'nullable|in:newest,closing,volume,popular', 'closing' => 'nullable|in:day,week']);
            if (! empty($filters['q'])) {
                $query->where('title', 'like', '%'.$filters['q'].'%');
            }
            foreach (['category', 'status'] as $filter) {
                if (! empty($filters[$filter])) {
                    $query->where($filter, $filters[$filter]);
                }
            }
            if (! empty($filters['closing'])) {
                $query->where('closes_at', '>', now())->where('closes_at', '<=', $filters['closing'] === 'day' ? now()->addDay() : now()->addWeek());
            }
            $sort = $filters['sort'] ?? 'newest';
            if ($sort === 'closing') $query->orderBy('closes_at');
            elseif ($sort === 'volume') $query->orderByDesc('volume');
            elseif ($sort === 'popular') $query->orderByDesc('participants');
            $data['markets'] = $query->orderByDesc('id')->paginate(12)->withQueryString();
            $data['filters'] = (object) $filters;
        }
        if ($page === 'markets') {
            $data['stats'] = [
                'volume' => (int) DB::table('positions')->sum('stake'),
                'active' => DB::table('positions')->where('status', 'open')->count(),
                'paid' => (int) DB::table('positions')->where('status', 'won')->sum('payout'),
            ];
        }
        if ($page === 'market') {
            $data['adminView'] = $request->is('admin/*');
            $market = DB::table('markets')->where('id', $id)->first();
            abort_unless($market, 404);
            Gate::authorize('view-market', $market);
            $data['market'] = $market;
            $data['volume'] = (int) DB::table('positions')->where('market_id', $id)->sum('stake');
            $data['participants'] = DB::table('positions')->where('market_id', $id)->distinct()->count('user_id');
        }
        if ($page === 'positions') {
            $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:open,won,lost,refunded']);
            $data['filters'] = (object) $filters;
            $data['positions'] = DB::table('positions')->join('markets', 'markets.id', '=', 'positions.market_id')
                ->leftJoin('fx_quotes', 'fx_quotes.id', '=', 'positions.fx_quote_id')
                ->where('positions.user_id', $user->id)->when($filters['status'] ?? null, fn ($q, $status) => $q->where('positions.status', $status))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('markets.title', 'like', '%'.$term.'%'))
                ->select('positions.*', 'markets.title', 'markets.closes_at', 'markets.result', 'fx_quotes.rate as fx_rate', 'fx_quotes.as_of as fx_as_of')->orderByDesc('positions.id')->paginate(20)->withQueryString();
        }
        if ($page === 'wallet') {
            $currency = DB::table('payment_wallets')->where('user_id', $user->id)->value('currency') ?? 'XAF';
            $data['ndsgpay'] = [
                'enabled' => app(\App\Modules\Payments\Ndsgpay::class)->ready(),
                'min_amount' => (string) config('payments.min_amount'), 'max_amount' => (string) config('payments.max_amount'),
                'scale' => \App\Modules\Money\Money::scale($currency),
                'currency' => $currency,
                'correspondents' => array_column(app(\App\Modules\Payments\Capabilities::class)->forAccount($user), 'correspondent'),
                'correspondent_labels' => collect(app(\App\Modules\Payments\Capabilities::class)->forAccount($user))
                    ->mapWithKeys(fn ($capability) => [$capability['correspondent'] => config('ndsgpay_operators.'.$capability['correspondent'].'.name', $capability['correspondent'])])->all(),
                'payoutsEnabled' => false,
                'balance' => (int) DB::table('payment_wallets')->where('user_id', $request->user()->id)->value('balance'),
                'payments' => DB::table('payments')->where('user_id', $request->user()->id)->latest('id')->limit(30)
                    ->get(['id', 'reference', 'kind', 'amount', 'currency', 'status', 'local_state', 'created_at'])->map(function ($payment) { $payment->amount = (string) $payment->amount; return $payment; }),
            ];
        }
        if (in_array($page, ['transactions', 'wallet'])) {
            $filters = $request->validate(['kind' => 'nullable|string|max:40', 'q' => 'nullable|string|max:100']);
            $data['filters'] = (object) $filters;
            $ledger = DB::table('ledger_transactions')->where('user_id', $user->id);
            $data['walletStats'] = [
                'engaged' => (int) DB::table('positions')->where('user_id', $user->id)->where('status', 'open')->sum(DB::raw('COALESCE(wallet_stake, stake)')),
                'gains' => (int) (clone $ledger)->where('kind', 'Gain')->sum('amount'),
                'deposits' => (int) (clone $ledger)->where('kind', 'Dépôt NDSGPay')->sum('amount'),
                'withdrawals' => (int) (clone $ledger)->where('kind', 'Retrait NDSGPay')->sum('amount'),
            ];
            $data['transactions'] = $ledger
                ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('reference', 'like', '%'.$term.'%'))
                ->orderByDesc('id')->paginate(20)->withQueryString();
        }
        if ($page === 'support') {
            $data['tickets'] = DB::table('support_tickets')->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))->orderByDesc('updated_at')->paginate(20);
        }
        if ($page === 'ticket') {
            $ticket = DB::table('support_tickets')->where('id', $id)->first();
            abort_unless($ticket, 404);
            Gate::authorize('access-ticket', $ticket);
            $data['ticket'] = $ticket;
            $data['tickets'] = DB::table('support_tickets')->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))->orderByDesc('updated_at')->paginate(20);
            if ($user->role === 'admin') $data['customer'] = DB::table('users')->select('name', 'email', 'role', 'created_at')->where('id', $ticket->user_id)->first();
            $data['messages'] = DB::table('support_messages')->join('users', 'users.id', '=', 'support_messages.user_id')->where('ticket_id', $id)
                ->select('support_messages.*', 'users.name', 'users.role')->orderBy('support_messages.id')->paginate(30);
        }
        if ($page === 'notifications') {
            $data['notifications'] = DB::table('platform_notifications')->where('user_id', $user->id)->orderByDesc('id')->paginate(30);
        }
        if ($page === 'admin-users') {
            $data['users'] = DB::table('users')->select('id', 'name', 'email', 'role', 'suspended', 'created_at')
                ->selectSub(DB::table('accounts')->selectRaw('COALESCE(SUM(balance), 0)')->whereColumn('user_id', 'users.id'), 'balance')
                ->selectSub(DB::table('payment_wallets')->select('currency')->whereColumn('user_id', 'users.id'), 'wallet_currency')
                ->orderByDesc('id')->paginate(20);
        }
        if ($page === 'admin') {
            $data['recentActivity'] = DB::table('ledger_transactions')->where('currency', 'XAF')->select('id', 'kind', 'amount', 'currency', 'created_at')->orderByDesc('id')->limit(5)->get();
            $data['stats'] = [
                'open_markets' => DB::table('markets')->where('status', 'open')->where('closes_at', '>', now())->count(),
                'new_users' => DB::table('users')->where('created_at', '>=', now()->subDays(7))->count(),
                'open_positions' => DB::table('positions')->where('status', 'open')->count(),
                'users' => DB::table('users')->count(),
                'pending' => DB::table('markets')->where('status', 'pending')->count(),
                'volume' => (int) DB::table('positions')->sum('stake'),
                'tickets' => DB::table('support_tickets')->where('status', 'open')->count(),
                'ledger_balance' => DB::table('ledger_entries')->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->get()->filter(fn ($group) => (int) $group->total !== 0)->count(),
            ];
        }
        if ($page === 'admin') {
            $data['currencyBalances'] = DB::table('payment_wallets')->select('currency')->selectRaw('SUM(balance) as balance')
                ->groupBy('currency')->orderBy('currency')->get()->map(function ($row) { $row->balance = (string) $row->balance; return $row; });
        }
        if (in_array($page, ['dashboard', 'admin'])) {
            $activityCurrency = $page === 'admin' ? 'XAF' : (DB::table('payment_wallets')->where('user_id', $user->id)->value('currency') ?? 'XAF');
            $daily = DB::table('ledger_transactions')->where('currency', $activityCurrency)
                ->when($page !== 'admin', fn ($q) => $q->where('user_id', $user->id))
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as label, SUM(amount) as value')
                ->groupByRaw('DATE(created_at)')->orderBy('label')->pluck('value', 'label');
            $data['activity'] = collect(range(6, 0))->map(function ($days) use ($daily) {
                $label = now()->subDays($days)->format('Y-m-d');
                return ['label' => $label, 'value' => (int) ($daily[$label] ?? 0)];
            })->values();
        }
        if (in_array($page, ['predictions', 'admin-pipeline'])) {
            try {
                $data['pipeline'] = app(PipelineClient::class)->read($page === 'admin-pipeline' ? 'markets' : 'predictions', null);
            } catch (\Throwable $exception) {
                if (! $exception instanceof PipelineUnavailable) {
                    report($exception);
                }
                $data['pipeline'] = ['status' => 'unavailable', 'data' => []];
            }
        }

        return Inertia::render('Platform', ['page' => $page, ...$data]);
    }

    public function readNotifications(Request $request)
    {
        DB::table('platform_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
