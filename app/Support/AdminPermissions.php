<?php

namespace App\Support;

/**
 * What a sub-admin can be allowed to do. A Super Admin (role 'admin') always has every permission;
 * a sub-admin only has the ones the Super Admin ticked. Access is decided from the route name, so a
 * new admin page that nobody has mapped yet is closed to sub-admins until it's added here.
 *
 * Two rules the master controls:
 *  - 'finance' is the only way to see where the profit is and how money goes: profit and cost
 *    reports, payout and level-wise income, product costs, plan BV and margins, GST, the cycle
 *    pool/payout totals and Settings. Without it none of those figures appear anywhere.
 *  - The catalog is read-only for a sub-admin ('catalog'). Adding or editing needs 'catalog.manage'.
 */
class AdminPermissions
{
    /** @var array<string, array<string, array{0: string, 1: string}>> group => key => [label, description] */
    public const GROUPS = [
        'Operations' => [
            'members' => ['Members & network', 'View members, their profiles and genealogy trees.'],
            'regions' => ['Regions', 'See each region, its tree and invite link, and create or edit regions.'],
            'orders' => ['Orders', 'View all orders and cancel unpaid ones.'],
            'payments' => ['Payments', 'Verify or reject payment submissions.'],
            'cycles' => ['Cycles', 'Create and approve compensation cycles, which posts income to members. Money totals stay hidden without Finance access.'],
            'withdrawals' => ['Withdrawals', 'Approve, reject and mark member withdrawals paid.'],
            'dispatch' => ['Dispatch', 'Build and release payout batches.'],
        ],
        'Catalog & content' => [
            'catalog' => ['Catalog — view only', 'See categories, products and plans. No cost prices, BV or margins.'],
            'catalog.manage' => ['Catalog — add & edit', 'Add, edit and delete categories and products. Plans also need Finance access, because price and BV are part of the money model.'],
            'messages' => ['Messages', 'Contact-form messages from the website.'],
            'content' => ['Homepage & testimonials', 'Edit the public website content.'],
        ],
        'Operational reports' => [
            'reports.pending' => ['Pending & cash report', 'Unverified payments, unsettled orders and withdrawals waiting.'],
            'reports.repurchase' => ['Repurchase report', 'New versus repeat buyers.'],
        ],
        'Finance' => [
            'finance' => ['Finance & money flow', 'Where the profit is and how money goes: Business overview, Sales & margin, Payouts, Level-wise income, Compliance, product costs, plan BV, GST and cycle totals, and Settings. Never visible without this.'],
        ],
    ];

    /** Permissions that expose money flow or change the money model. Shown with a warning on the form. */
    public const SENSITIVE = ['finance', 'catalog.manage'];

    /** Route-name prefix => permission(s) required. 'super' means Super Admin only and can never be granted. */
    private const ROUTE_MAP = [
        'admin.team.' => 'super',
        'admin.messages.' => 'messages',
        'admin.members.' => 'members',
        'admin.regions.' => 'regions',
        'admin.orders.' => 'orders',
        'admin.payments.' => 'payments',
        'admin.cycles.' => 'cycles',
        'admin.withdrawals.' => 'withdrawals',
        'admin.dispatch.' => 'dispatch',
        'admin.site-content.' => 'content',
        'admin.testimonials.' => 'content',
        'admin.settings.' => 'finance',
        'admin.guide.' => 'finance',
        'admin.reports.overview' => 'finance',
        'admin.reports.sales' => 'finance',
        'admin.reports.compliance' => 'finance',
        'admin.reports.payouts' => 'finance',
        'admin.reports.levels' => 'finance',
        'admin.reports.pending' => 'reports.pending',
        'admin.reports.repurchase' => 'reports.repurchase',
    ];

    /** The catalog: listing is 'catalog'; any change needs 'catalog.manage' (plans also need 'finance'). */
    private const CATALOG = ['admin.categories.' => false, 'admin.products.' => false, 'admin.plans.' => true];

    /** Pages every signed-in staff member can reach. */
    private const ALWAYS = ['admin.dashboard', 'admin.logout', 'admin.account.password', 'admin.account.password.update'];

    /** @return list<string> */
    public static function keys(): array
    {
        return collect(self::GROUPS)->flatMap(fn ($items) => array_keys($items))->values()->all();
    }

    /**
     * What a route needs: [] = open to all staff, ['super'] = Super Admin only, otherwise every listed
     * permission is required. A route under admin.* that isn't mapped is Super Admin only (fail closed).
     *
     * @return list<string>
     */
    public static function requirementsFor(?string $name): array
    {
        if ($name === null || ! str_starts_with($name, 'admin.') || in_array($name, self::ALWAYS, true)) {
            return [];
        }

        foreach (self::CATALOG as $prefix => $needsFinance) {
            if (str_starts_with($name, $prefix)) {
                if (str_ends_with($name, '.index')) {
                    return ['catalog'];
                }

                return $needsFinance ? ['catalog.manage', 'finance'] : ['catalog.manage'];
            }
        }

        foreach (self::ROUTE_MAP as $prefix => $permission) {
            if (str_starts_with($name, $prefix)) {
                return [$permission];
            }
        }

        return ['super'];
    }
}
