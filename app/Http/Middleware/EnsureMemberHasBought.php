<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until a member's first payment is approved they can only see the plans and their own orders. Everything else
 * (dashboard, wallet, team, reports) opens once they have bought.
 */
class EnsureMemberHasBought
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user('member');

        if ($member && ! $member->hasBought()) {
            return redirect()->route('member.plans.index');
        }

        return $next($request);
    }
}
