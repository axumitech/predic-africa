<?php

namespace App\Http\Controllers;

use App\Modules\Predictions\Infrastructure\PipelineClient;
use App\Modules\Predictions\Infrastructure\PipelineUnavailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function page(Request $request, string $page = 'markets', ?int $id = null)
    {
        $user = $request->user();
        $data = [];
        if (str_starts_with($page, 'admin')) {
            abort_unless($user?->role === 'admin', 403);
        }
        if (in_array($page, ['markets', 'admin-markets', 'creator'])) {
            $query = DB::table('markets');
            if ($page === 'markets') {
                $query->whereIn('status', ['open', 'resolved', 'void']);
            } elseif ($page === 'creator') {
                $query->where('creator_id', $user->id);
            }
            $filters = $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:30', 'status' => 'nullable|string|max:30']);
            if (! empty($filters['q'])) {
                $query->where('title', 'like', '%'.$filters['q'].'%');
            }
            foreach (['category', 'status'] as $filter) {
                if (! empty($filters[$filter])) {
                    $query->where($filter, $filters[$filter]);
                }
            }
            $data['markets'] = $query->orderByDesc('id')->paginate(12)->withQueryString();
            $data['filters'] = $filters;
        }
        if ($page === 'markets') {
            $data['stats'] = [
                'volume' => (int) DB::table('positions')->sum('stake'),
                'active' => DB::table('positions')->where('status', 'open')->count(),
                'paid' => (int) DB::table('ledger_transactions')->where('kind', 'Gain')->sum('amount'),
            ];
        }
        if ($page === 'market') {
            $market = DB::table('markets')->where('id', $id)->first();
            abort_unless($market, 404);
            abort_unless(in_array($market->status, ['open', 'resolved', 'void']) || $market->creator_id === $user?->id || $user?->role === 'admin', 403);
            $data['market'] = $market;
            $data['volume'] = (int) DB::table('positions')->where('market_id', $id)->sum('stake');
            $data['participants'] = DB::table('positions')->where('market_id', $id)->distinct()->count('user_id');
        }
        if ($page === 'positions') {
            $data['positions'] = DB::table('positions')->join('markets', 'markets.id', '=', 'positions.market_id')
                ->where('positions.user_id', $user->id)->select('positions.*', 'markets.title')->orderByDesc('positions.id')->paginate(20);
        }
        if (in_array($page, ['transactions', 'wallet'])) {
            $data['transactions'] = DB::table('ledger_transactions')->where('user_id', $user->id)->orderByDesc('id')->paginate(20);
        }
        if ($page === 'support') {
            $data['tickets'] = DB::table('support_tickets')->when($user->role !== 'admin', fn ($q) => $q->where('user_id', $user->id))->orderByDesc('updated_at')->paginate(20);
        }
        if ($page === 'ticket') {
            $ticket = DB::table('support_tickets')->where('id', $id)->first();
            abort_unless($ticket, 404);
            abort_unless($user->role === 'admin' || $ticket->user_id === $user->id, 403);
            $data['ticket'] = $ticket;
            $data['messages'] = DB::table('support_messages')->join('users', 'users.id', '=', 'support_messages.user_id')->where('ticket_id', $id)
                ->select('support_messages.*', 'users.name', 'users.role')->orderBy('support_messages.id')->paginate(30);
        }
        if ($page === 'notifications') {
            $data['notifications'] = DB::table('platform_notifications')->where('user_id', $user->id)->orderByDesc('id')->paginate(30);
        }
        if ($page === 'admin-users') {
            $data['users'] = DB::table('users')->select('id', 'name', 'email', 'role', 'suspended', 'created_at')->orderByDesc('id')->paginate(20);
        }
        if ($page === 'admin') {
            $data['stats'] = [
                'users' => DB::table('users')->count(),
                'pending' => DB::table('markets')->where('status', 'pending')->count(),
                'volume' => (int) DB::table('positions')->sum('stake'),
                'tickets' => DB::table('support_tickets')->where('status', 'open')->count(),
                'ledger_balance' => (int) DB::table('ledger_entries')->sum('amount'),
            ];
        }
        if (in_array($page, ['predictions', 'admin-pipeline'])) {
            try {
                $data['pipeline'] = app(PipelineClient::class)->read($page === 'admin-pipeline' ? 'markets' : 'predictions', null);
            } catch (\Throwable $exception) {
                if (! $exception instanceof PipelineUnavailable) {
                    report($exception);
                }
                $data['pipeline'] = ['status' => 'unavailable', 'data' => [], 'simulation' => true];
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
