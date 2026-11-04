<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * The Super Admin reads the whole track record of staff: sign-ins and sign-outs, refused attempts, every change with
 * what it was and what it became, and every download. Filter it, page through it, download it. There is nothing here
 * that changes or deletes a line.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    private const CSV_LIMIT = 20000;

    public function index(Request $request): View
    {
        if (! Schema::hasTable('audit_logs')) {
            return view('admin.audit-log.index', ['setupNeeded' => true]);
        }

        $filters = $this->filters($request);
        $extended = AuditLog::extended();

        return view('admin.audit-log.index', [
            'entries' => $this->query($filters, $extended)->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'q' => $filters['q'],
            'extended' => $extended,
            'summary' => $this->summary($extended),
            'staff' => $this->staff(),
            'staffList' => User::whereIn('role', ['admin', 'sub_admin'])->orderBy('name')->get(['id', 'name']),
            'categories' => AuditLog::CATEGORIES,
        ]);
    }

    /** The log, as a spreadsheet, with whatever filters are on. Downloading it is itself written to the log. */
    public function csv(Request $request): StreamedResponse
    {
        abort_unless(Schema::hasTable('audit_logs'), 404);

        $extended = AuditLog::extended();
        $rows = $this->query($this->filters($request), $extended)->orderByDesc('id')->limit(self::CSV_LIMIT)->get();

        return response()->streamDownload(function () use ($rows, $extended) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Who', 'Role', 'Category', 'Outcome', 'Action', 'Item', 'Details', 'Address']);

            foreach ($rows as $e) {
                fputcsv($out, array_map([$this, 'cell'], [
                    $e->created_at->format('Y-m-d H:i:s'), $e->user_name ?? '', $extended ? ($e->user_role ?? '') : '', $extended ? ($e->category ?? '') : '',
                    $extended ? ($e->outcome ?? '') : '', $e->action, $e->subject ?? '', $e->details ?? '', $e->ip ?? '',
                ]));
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** A cell starting with = + - @ would run as a formula in a spreadsheet: defuse it with a leading apostrophe. */
    private function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** Only values that make sense are used; anything else is ignored rather than failing. */
    private function filters(Request $request): array
    {
        $date = function (mixed $v): ?Carbon {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $v)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        $category = $request->query('category');
        $outcome = $request->query('outcome');

        return [
            'q' => is_string($request->query('q')) ? trim($request->query('q')) : '',
            'user' => ctype_digit((string) $request->query('user', '')) ? (int) $request->query('user') : null,
            'category' => is_string($category) && array_key_exists($category, AuditLog::CATEGORIES) ? $category : null,
            'outcome' => is_string($outcome) && in_array($outcome, AuditLog::OUTCOMES, true) ? $outcome : null,
            'from' => $date($request->query('from')),
            'to' => $date($request->query('to')),
        ];
    }

    private function query(array $f, bool $extended): Builder
    {
        $like = fn (string $s) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s).'%';

        return AuditLog::query()
            ->when($f['q'] !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw("action LIKE ? ESCAPE '!'", [$like($f['q'])])
                ->orWhereRaw("user_name LIKE ? ESCAPE '!'", [$like($f['q'])])
                ->orWhereRaw("subject LIKE ? ESCAPE '!'", [$like($f['q'])])
                ->orWhereRaw("details LIKE ? ESCAPE '!'", [$like($f['q'])])
                ->orWhereRaw("ip LIKE ? ESCAPE '!'", [$like($f['q'])])))
            ->when($f['user'], fn ($query, $id) => $query->where('user_id', $id))
            ->when($f['from'], fn ($query, Carbon $d) => $query->where('created_at', '>=', $d))
            ->when($f['to'], fn ($query, Carbon $d) => $query->where('created_at', '<=', $d->copy()->endOfDay()))
            ->when($extended ? $f['category'] : null, fn ($query, $c) => $query->where('category', $c))
            ->when($extended ? $f['outcome'] : null, fn ($query, $o) => $query->where('outcome', $o));
    }

    /** The last day at a glance. */
    private function summary(bool $extended): array
    {
        $since = now()->subDay();
        $recent = fn () => AuditLog::where('created_at', '>=', $since);

        return [
            'events' => $recent()->count(),
            'failed_sign_ins' => $extended ? $recent()->where('outcome', 'failed')->count() : $recent()->where('action', 'admin.login.failed')->count(),
            'denied' => $extended ? $recent()->where('outcome', 'denied')->count() : 0,
            'staff_active' => $recent()->whereNotNull('user_id')->distinct()->count('user_id'),
        ];
    }

    /** Every staff member with their last sign-in, so it is easy to see who has not been in, or from where. */
    private function staff(): array
    {
        $users = User::whereIn('role', ['admin', 'sub_admin'])->orderBy('name')->get();
        $lastIds = AuditLog::where('action', 'admin.login')->whereIn('user_id', $users->pluck('id'))->selectRaw('user_id, MAX(id) as last_id')->groupBy('user_id')->pluck('last_id', 'user_id');
        $lastRows = AuditLog::whereIn('id', $lastIds->values())->get()->keyBy('user_id');

        return $users->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->role,
            'active' => (bool) $u->is_active,
            'last_at' => $lastRows[$u->id]->created_at ?? null,
            'last_ip' => $lastRows[$u->id]->ip ?? null,
        ])->all();
    }
}
