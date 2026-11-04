<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes down what a signed-in staff member did in the admin area, once it went through: every change (anything but a
 * page view) and every download of a CSV or export. Only the names of the fields that were sent are kept, never their
 * values. What those changes were, old value to new, is written by the models themselves (see AuditLog::modelChanged).
 * Refused attempts are written by EnsureAdmin, which is where they are refused.
 */
class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user('web') || $response->getStatusCode() >= 400) {
            return $response;
        }

        $name = (string) ($request->route()?->getName() ?? $request->path());
        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        $isDownload = $request->isMethod('GET') && (str_contains($name, '.csv') || str_contains($name, 'export'));

        if ($isWrite || $isDownload) {
            $subject = collect($request->route()?->parameters() ?? [])
                ->filter(fn ($p) => $p instanceof Model)
                ->map(fn (Model $m) => class_basename($m).' #'.$m->getKey())->implode(', ');

            AuditLog::record(
                $name,
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
