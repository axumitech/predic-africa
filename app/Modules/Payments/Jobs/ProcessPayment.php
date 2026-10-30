<?php

namespace App\Modules\Payments\Jobs;

use App\Modules\Payments\Inbox;
use App\Modules\Payments\Outbox;
use App\Modules\Payments\Payments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(public string $work, public int $id)
    {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        match ($this->work) {
            'inbox' => app(Inbox::class)->process($this->id),
            'outbox' => app(Outbox::class)->deliver($this->id),
            'refresh' => $this->refresh(),
            default => throw new \LogicException('Unknown payment work.'),
        };
    }

    private function refresh(): void
    {
        $payment = DB::table('payments')->find($this->id);
        if ($payment) {
            app(Payments::class)->refresh($payment);
        }
    }
}
