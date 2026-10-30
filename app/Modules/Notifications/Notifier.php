<?php

namespace App\Modules\Notifications;

use Illuminate\Support\Facades\DB;

class Notifier
{
    public static function send(int $userId, string $message): void
    {
        // Read the preference and insert in one statement, including queued deliveries.
        DB::table('platform_notifications')->insertUsing(['user_id', 'message', 'created_at'],
            DB::table('users')->select('id')->selectRaw('? as message, ? as created_at', [$message, now()])
                ->where('id', $userId)->where('notifications_enabled', true));
    }
}
