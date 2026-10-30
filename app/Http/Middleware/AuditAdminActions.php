<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes down every change a signed-in staff member makes in the admin area (anything but a page view), once it went
 * through. Only the names of the fields that were sent are kept, never their values.
 */
class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $response->getStatusCode() < 400 && $request->user('web')) {
            $subject = collect($request->route()?->parameters() ?? [])
                ->filter(fn ($p) => $p instanceof Model)
                ->map(fn (Model $m) => class_basename($m).' #'.$m->getKey())->implode(', ');

            AuditLog::record(
                (string) ($request->route()?->getName() ?? $request->path()),
                $subject ?: null,
                $request->method(),
                '/'.ltrim($request->path(), '/'),
                array_values(array_diff(array_keys($request->except(['_token', '_method'])), ['_token', '_method'])),
                $request->user('web'),
                $request->ip(),
            );
        }

        return $response;
    }
}
