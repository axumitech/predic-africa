<?php

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\DB;

class Notifier
{
    public static function send(int $userId, string $message): void
    {
        DB::table('platform_notifications')->insert(['user_id' => $userId, 'message' => $message, 'created_at' => now()]);
    }
}
