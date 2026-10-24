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

        // A cycle settles every region at once and a batch pays members from every region, so someone
        // limited to some regions must not run them (they can still view them).
        if ($route && $user->isRegionRestricted() && in_array($route, self::ALL_REGION_ACTIONS, true)) {
            abort(403, 'This action affects every region, so it is limited to people with access to all regions.');
        }

        return $next($request);
    }

    private const ALL_REGION_ACTIONS = [
        'admin.cycles.store', 'admin.cycles.approve',
        'admin.dispatch.store', 'admin.dispatch.release',
        'admin.regions.create', 'admin.regions.store',
    ];
}
