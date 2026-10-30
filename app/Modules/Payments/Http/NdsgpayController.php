<?php

namespace App\Modules\Payments\Http;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Jobs\ProcessPayment;
use App\Modules\Payments\PaymentPolicy;
use App\Modules\Payments\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NdsgpayController extends Controller
{
    public function store(StorePaymentRequest $request, Payments $payments)
    {
        $payment = $payments->create($request->user()->id, $request->operation());

        return back()->with('success', match ($payment->local_state) {
            'initiation_unknown', 'initiating' => 'Vérification nécessaire. Ne relancez pas le paiement. Référence : '.$payment->reference,
            'failed' => 'Opération non envoyée ou échec confirmé. Consultez son statut.',
            'completed' => 'Paiement déjà confirmé.',
            default => 'Demande initiée. Confirmez sur votre téléphone. Le paiement attend sa vérification.',
        });
    }

    public function refresh(Request $request, int $id)
    {
        $payment = DB::table('payments')->where('id', $id)->firstOrFail();
        abort_unless(app(PaymentPolicy::class)->view($request->user(), $payment), 404);
        // Browser never drives the provider directly or bypasses the next-check backoff.
        ProcessPayment::dispatch('refresh', $payment->id);

        return back()->with('success', 'Vérification programmée. Le statut sera actualisé après confirmation.');
    }
}
