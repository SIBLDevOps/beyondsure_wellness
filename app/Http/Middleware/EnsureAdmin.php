<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the whole admin console. Any admin or sub-admin who is switched on gets in; what they can
 * open is then decided from the route name (see AdminPermissions), so every page, form post and CSV
 * export is covered without each route having to remember to ask.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdminStaff()) {
            abort(403);
        }

        $route = $request->route()?->getName();

        if ($route && ! $user->canAccessRoute($route)) {
            abort(403, 'You do not have access to this section. Ask your Super Admin to grant it.');
        }

        return $next($request);
    }
}
