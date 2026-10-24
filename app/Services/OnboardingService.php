<?php

namespace App\Services;

use App\Models\Member;

/**
 * The "getting started" path for a new member. Nothing is stored: every step is worked out from
 * what the member has actually done, so it can never drift out of date. The same list drives the
 * member's checklist and the admin's "who needs help" view, so both sides see one truth.
 */
class OnboardingService
{
    /**
     * @return list<array{key: string, title: string, text: string, done: bool, route: string, cta: string, admin: string}>
     */
    public function steps(Member $member): array
    {
        $paid = (int) ($member->paid_orders_count ?? $member->orders()->whereIn('status', ['paid', 'settled'])->count());
        $awaiting = $member->orders()->where('status', 'pending_payment')->exists();
        $directs = (int) ($member->sponsored_members_count ?? $member->sponsoredMembers()->count());
        $earned = $member->ledgerEntries()->where('amount', '>', 0)->exists();

        return [
            [
                'key' => 'joined', 'title' => 'Create your account', 'done' => true,
                'text' => 'You are in. Your member code is your identity in the network.',
                'route' => 'member.dashboard', 'cta' => 'Done',
                'admin' => 'Account created.',
            ],
            [
                'key' => 'purchase', 'title' => $awaiting && ! $paid ? 'Complete your payment' : 'Choose and buy a plan',
                'done' => $paid > 0,
                'text' => $awaiting && ! $paid
                    ? 'Your order is waiting for payment. Pay to activate your account.'
                    : 'Your first plan activates your account and starts your income.',
                'route' => $awaiting && ! $paid ? 'member.orders.index' : 'member.plans.index',
                'cta' => $awaiting && ! $paid ? 'Pay now' : 'See plans',
                'admin' => $awaiting && ! $paid
                    ? 'Has an unpaid order. If they paid by UPI/bank, verify the payment; otherwise remind them to pay.'
                    : 'Has not bought a plan. Suggest one and share the plans page.',
            ],
            [
                'key' => 'bank', 'title' => 'Add payout details', 'done' => $member->hasBankDetails(),
                'text' => 'Save your bank account so you can withdraw earnings later.',
                'route' => 'member.wallet.index', 'cta' => 'Add bank details',
                'admin' => 'No bank details saved. They cannot withdraw until they add them (Wallet page).',
            ],
            [
                'key' => 'invite', 'title' => 'Invite your first member', 'done' => $directs > 0,
                'text' => 'Share your Left or Right invite link. Sponsor income starts with your first direct.',
                'route' => 'member.downline.index', 'cta' => 'Get invite links',
                'admin' => 'Has not introduced anyone yet. Remind them about their Left/Right invite links.',
            ],
            [
                'key' => 'income', 'title' => 'Earn your first income', 'done' => $earned,
                'text' => 'Income is posted when a cycle is approved. Self and sponsor income come first.',
                'route' => 'member.wallet.index', 'cta' => 'View wallet',
                'admin' => 'No income yet. It appears after a cycle that includes their paid orders is approved.',
            ],
        ];
    }

    /** @return array{steps: array, done: int, total: int, percent: int, next: ?array, complete: bool} */
    public function progress(Member $member): array
    {
        $steps = $this->steps($member);
        $done = count(array_filter($steps, fn ($s) => $s['done']));
        $next = collect($steps)->first(fn ($s) => ! $s['done']);

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'percent' => (int) round($done / count($steps) * 100),
            'next' => $next,
            'complete' => $next === null,
        ];
    }
}
