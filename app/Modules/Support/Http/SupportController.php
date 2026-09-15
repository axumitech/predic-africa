<?php

namespace App\Modules\Support\Http;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['subject' => 'required|string|min:5|max:200', 'body' => 'required|string|min:10|max:10000']);
        $id = DB::transaction(function () use ($request, $data) {
            $id = DB::table('support_tickets')->insertGetId(['user_id' => $request->user()->id, 'subject' => $data['subject'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('support_messages')->insert(['ticket_id' => $id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'created_at' => now()]);

            return $id;
        });

        return redirect("/support/$id")->with('success', 'Ticket créé.');
    }

    public function reply(Request $request, int $id)
    {
        $data = $request->validate(['body' => 'required|string|min:1|max:10000', 'status' => ['required', Rule::in(['open', 'closed'])]]);
        DB::transaction(function () use ($request, $data, $id) {
            $ticket = DB::table('support_tickets')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ticket, 404);
            abort_unless($request->user()->role === 'admin' || $ticket->user_id === $request->user()->id, 403);
            DB::table('support_messages')->insert(['ticket_id' => $id, 'user_id' => $request->user()->id, 'body' => $data['body'], 'created_at' => now()]);
            DB::table('support_tickets')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);
            if ($request->user()->id !== $ticket->user_id) {
                Notifier::send($ticket->user_id, "Une réponse est disponible pour le ticket #$id.");
            }
        });

        return back()->with('success', 'Réponse enregistrée.');
    }
}
