<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * One line in the record of what staff did. Rows are only ever added: changing or deleting one is refused, in code
 * as well as by having no screen or route that could do it. Only the NAMES of the fields that were sent are kept,
 * never what was typed, so passwords and personal data cannot end up here.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'user_name', 'action', 'method', 'path', 'subject', 'details', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Audit log entries cannot be deleted.'));
    }

    /** Writes one line. Never throws: a problem with the log must not stop the action being logged. */
    public static function record(string $action, ?string $subject = null, ?string $method = null, ?string $path = null, array $fields = [], ?User $user = null, ?string $ip = null): ?self
    {
        try {
            $user ??= Auth::guard('web')->user();

            return static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => mb_substr($action, 0, 150),
                'method' => $method,
                'path' => $path ? mb_substr($path, 0, 255) : null,
                'subject' => $subject ? mb_substr($subject, 0, 120) : null,
                'details' => $fields ? mb_substr(implode(', ', $fields), 0, 1000) : null,
                'ip' => $ip ?? request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log entry "'.$action.'" was not written: '.$e->getMessage());

            return null;
        }
    }
}
