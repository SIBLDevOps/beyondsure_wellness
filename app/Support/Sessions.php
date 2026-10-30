<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Sessions
{
    /**
     * Ends every sign-in a staff member has (except the one making the change). Used after a password change or reset, so
     * someone who already has a session on another device, or who stole one, is signed out. Works with the database
     * session driver; with any other driver there is nothing to look up and this does nothing.
     */
    public static function endOthersFor(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
