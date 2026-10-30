<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\MemberMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** A member emails someone an invite link with their own sponsor code, the leg and the plan already chosen. */
class InviteController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $member = Auth::guard('member')->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'side' => ['nullable', Rule::in(['left', 'right'])],
            // Only an active plan from the sponsor's own region: that is where the new person will join.
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')->where(fn ($q) => $q->where('is_active', true)->where('region_id', $member->region_id))],
        ], [
            'email.required' => 'Enter the email address to send the invite to.',
            'email.email' => 'Enter a valid email address.',
            'plan_id.exists' => 'Choose one of the plans offered in your region.',
        ]);

        $plan = ! empty($data['plan_id']) ? Plan::find($data['plan_id']) : null;
        $url = route('register', array_filter([
            'sponsor' => $member->member_code,
            'side' => $data['side'] ?? null,
            'plan' => $plan?->id,
        ]));

        app(MemberMailer::class)->invite($member, $data['email'], $url, $plan, $data['name'] ?? null);

        return back()->with('status', 'Invite sent to '.$data['email'].'.');
    }
}
