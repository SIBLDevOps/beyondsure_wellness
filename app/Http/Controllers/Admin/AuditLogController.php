<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The Super Admin reads what staff did. There is nothing here that changes or deletes a line. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            return view('admin.audit-log.index', ['setupNeeded' => true]);
        }

        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $like = fn (string $s) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s).'%';

        $entries = AuditLog::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw("action LIKE ? ESCAPE '!'", [$like($q)])
                ->orWhereRaw("user_name LIKE ? ESCAPE '!'", [$like($q)])
                ->orWhereRaw("subject LIKE ? ESCAPE '!'", [$like($q)])
                ->orWhereRaw("ip LIKE ? ESCAPE '!'", [$like($q)])))
            ->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.audit-log.index', ['entries' => $entries, 'q' => $q]);
    }
}
