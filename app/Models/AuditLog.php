<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One line in the record of what staff did. Rows are only ever added: changing or deleting one is refused in code and
 * by the database itself, and there is no screen or route that could do it.
 *
 * Two kinds of line sit side by side: what a person pressed (the route, and the NAMES of the fields they sent), and what
 * that changed (for settings, cycles, prices, payments, staff accounts and the like: the old value and the new one).
 * Free text people typed (messages, addresses) and anything secret (passwords, bank details) is never written down.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'category', 'outcome', 'method', 'path', 'subject', 'details', 'ip', 'user_agent', 'request_id', 'created_at',
    ];

    /** The kinds of line people can filter by, with a label. */
    public const CATEGORIES = [
        'sign-in' => 'Sign-ins', 'security' => 'Security', 'settings' => 'Settings', 'cycles' => 'Cycles', 'money' => 'Money',
        'catalog' => 'Catalog', 'members' => 'Members', 'staff' => 'Staff', 'content' => 'Content', 'export' => 'Downloads', 'other' => 'Other',
    ];

    public const OUTCOMES = ['success', 'failed', 'denied'];

    /** Field names whose values are never written, only the fact that they changed. */
    private const SECRET = '/password|secret|token|recovery|otp|account_number|ifsc|upi|bank_|pay_|two_factor/i';

    private static ?bool $extended = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Audit log entries cannot be deleted.'));
    }

    /** Have the newer columns (role, category, outcome, ...) been added by the database update? */
    public static function extended(): bool
    {
        try {
            return self::$extended ??= Schema::hasColumn('audit_logs', 'category');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetSchemaCache(): void
    {
        self::$extended = null;
    }

    /** The kind of line an action is, so the log can be filtered. */
    public static function categoryFor(string $action): string
    {
        $is = fn (string ...$prefixes) => collect($prefixes)->contains(fn ($p) => str_starts_with($action, $p));

        return match (true) {
            str_ends_with($action, '.denied') => 'security',
            $is('admin.login') || $action === 'admin.logout' => 'sign-in',
            str_contains($action, '.csv') || str_contains($action, 'export') => 'export',
            $is('admin.account.password', 'admin.account.two-factor', 'admin.two-factor') || in_array($action, ['admin.team.password', 'admin.team.two-factor-reset'], true) => 'security',
            $is('admin.settings', 'admin.site-content', 'change.setting') => 'settings',
            $is('admin.cycles', 'change.cycle') => 'cycles',
            $is('admin.payments', 'admin.withdrawals', 'admin.orders', 'admin.dispatch', 'admin.refunds', 'change.payment', 'change.withdrawal', 'change.order') => 'money',
            $is('admin.plans', 'admin.products', 'admin.categories', 'change.plan', 'change.product', 'change.category', 'change.rank') => 'catalog',
            $is('admin.members', 'admin.regions', 'admin.activation', 'change.member', 'change.region') => 'members',
            $is('admin.team', 'change.user') => 'staff',
            $is('admin.announcements', 'admin.testimonials', 'admin.messages') => 'content',
            default => 'other',
        };
    }

    /** One id per web request, so the press of a button and the changes it caused can be told apart from other requests. */
    private static function requestId(): string
    {
        $request = request();
        $id = $request->attributes->get('audit_request_id');

        if (! $id) {
            $id = (string) Str::uuid();
            $request->attributes->set('audit_request_id', $id);
        }

        return $id;
    }

    /**
     * Writes one line. Never throws: a problem with the log must not stop the action being logged.
     *
     * @param  list<string>  $fields  names of the fields that were sent (never their values)
     */
    public static function record(string $action, ?string $subject = null, ?string $method = null, ?string $path = null, array $fields = [], ?User $user = null, ?string $ip = null, ?string $details = null, string $outcome = 'success'): ?self
    {
        try {
            $user ??= Auth::guard('web')->user();
            $text = $details ?? ($fields ? implode(', ', $fields) : null);

            $line = [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => mb_substr($action, 0, 150),
                'method' => $method,
                'path' => $path ? mb_substr($path, 0, 255) : null,
                'subject' => $subject ? mb_substr($subject, 0, 120) : null,
                'details' => $text !== null ? mb_substr($text, 0, 2000) : null,
                'ip' => $ip ?? request()->ip(),
                'created_at' => now(),
            ];

            if (self::extended()) {
                $line += [
                    'user_role' => $user?->role,
                    'category' => self::categoryFor($action),
                    'outcome' => $outcome,
                    'user_agent' => ($agent = request()->userAgent()) ? mb_substr($agent, 0, 255) : null,
                    'request_id' => self::requestId(),
                ];
            }

            return static::create($line);
        } catch (\Throwable $e) {
            Log::warning('Audit log entry "'.$action.'" was not written: '.$e->getMessage());

            return null;
        }
    }

    // ------------------------------------------------------------------------------------------ what a change was

    /**
     * Called when a model that is tracked is created, changed or deleted. Only changes made by a signed-in staff member
     * are written down (a member buying a plan, or the system running, is not staff activity), and only for the fields the
     * model lists in auditFields().
     */
    public static function modelChanged(Model $model, string $event): void
    {
        try {
            $user = Auth::guard('web')->user();
            if (! $user) {
                return;
            }

            $name = Str::snake(class_basename($model));
            $lines = $model instanceof Setting ? self::settingLines($model, $event) : self::fieldLines($model, $event);

            if ($lines === null || ($lines === [] && $event !== 'deleted')) {
                return;
            }

            static::record(
                "change.{$name}.{$event}",
                method_exists($model, 'auditSubject') ? $model->auditSubject() : class_basename($model).' #'.$model->getKey(),
                request()->method(),
                '/'.ltrim(request()->path(), '/'),
                [],
                $user,
                request()->ip(),
                $lines ? implode('; ', $lines) : null,
            );
        } catch (\Throwable $e) {
            Log::warning('Audit change for '.class_basename($model).' was not written: '.$e->getMessage());
        }
    }

    /** @return list<string>|null */
    private static function fieldLines(Model $model, string $event): ?array
    {
        $fields = method_exists($model, 'auditFields') ? $model->auditFields() : [];
        $lines = [];

        if ($event === 'updated') {
            foreach ($model->getChanges() as $key => $new) {
                if (! in_array($key, $fields, true)) {
                    continue;
                }
                $old = $model->getOriginal($key);
                if (self::show($key, $old) === self::show($key, $new)) {
                    continue;
                }
                $lines[] = self::secret($key) ? "{$key}: changed (value hidden)" : "{$key}: ".self::show($key, $old).' → '.self::show($key, $new);
            }
        } elseif ($event === 'created') {
            foreach ($fields as $key) {
                $value = $model->getAttribute($key);
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                $lines[] = self::secret($key) ? "{$key}: set (value hidden)" : "{$key}: ".self::show($key, $value);
            }
        }

        return $lines;
    }

    /** @return list<string>|null */
    private static function settingLines(Setting $setting, string $event): ?array
    {
        if ($event === 'deleted') {
            return [];
        }

        if ($event === 'updated') {
            if (! array_key_exists('value', $setting->getChanges())) {
                return null;
            }
            $old = $setting->getOriginal('value');
            $new = $setting->value;

            return [self::secret($setting->key) ? 'changed (value hidden)' : self::show('value', $old).' → '.self::show('value', $new)];
        }

        return [self::secret($setting->key) ? 'set (value hidden)' : 'set to '.self::show('value', $setting->value)];
    }

    private static function secret(string $key): bool
    {
        return (bool) preg_match(self::SECRET, $key);
    }

    /** A value as a person would read it: yes/no, a list, no pointless trailing zeros, a sensible length. */
    private static function show(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value) || (str_starts_with($key, 'is_') && in_array((string) $value, ['0', '1'], true))) {
            return ((bool) $value) ? 'yes' : 'no';
        }
        if (is_string($value) && str_starts_with($value, '[') && is_array($list = json_decode($value, true))) {
            $value = $list;
        }
        if (is_array($value)) {
            return $value === [] ? '—' : Str::limit(implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)), 80, '…');
        }

        $text = (string) $value;
        if (is_numeric($text) && str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return Str::limit($text, 80, '…');
    }
}
