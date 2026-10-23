<?php

namespace App\Support;

/**
 * What a sub-admin can be allowed to see. A Super Admin (role 'admin') always has every permission;
 * a sub-admin only has the ones the Super Admin ticked. Access is decided from the route name, so a
 * new admin page that nobody has mapped yet is closed to sub-admins until it's added here.
 */
class AdminPermissions
{
    /** @var array<string, array<string, array{0: string, 1: string}>> group => key => [label, description] */
    public const GROUPS = [
        'Operations' => [
            'members' => ['Members & network', 'View members, their profiles, income and genealogy trees.'],
            'orders' => ['Orders', 'View all orders and cancel unpaid ones.'],
            'payments' => ['Payments', 'Verify or reject payment submissions.'],
            'cycles' => ['Cycles', 'Create and approve compensation cycles, which posts income to members.'],
            'withdrawals' => ['Withdrawals', 'Approve, reject and mark member withdrawals paid.'],
            'dispatch' => ['Dispatch', 'Build and release payout batches.'],
        ],
        'Catalog & content' => [
            'catalog' => ['Categories, products & plans', 'Includes product cost prices, plan BV and the payout matrix.'],
            'messages' => ['Messages', 'Contact-form messages from the website.'],
            'content' => ['Homepage & testimonials', 'Edit the public website content.'],
        ],
        'Reports' => [
            'reports.payouts' => ['Payouts report', 'What members were paid, top earners and cycles.'],
            'reports.levels' => ['Level-wise income report', 'Income by level and by member.'],
            'reports.pending' => ['Pending & cash report', 'Unverified payments, unsettled orders and withdrawals waiting.'],
            'reports.repurchase' => ['Repurchase report', 'New versus repeat buyers.'],
            'reports.profit' => ['Profit reports', 'Business overview, Sales & products margin, GST and Compliance — where the money goes and what the company keeps.'],
        ],
        'System' => [
            'settings' => ['Settings', 'Compensation rules, GST, circuit breaker, ranks and registration rules.'],
        ],
    ];

    /** Route-name prefix => permission. 'super' means Super Admin only and can never be granted. */
    private const ROUTE_MAP = [
        'admin.team.' => 'super',
        'admin.categories.' => 'catalog',
        'admin.products.' => 'catalog',
        'admin.plans.' => 'catalog',
        'admin.messages.' => 'messages',
        'admin.members.' => 'members',
        'admin.orders.' => 'orders',
        'admin.payments.' => 'payments',
        'admin.cycles.' => 'cycles',
        'admin.withdrawals.' => 'withdrawals',
        'admin.dispatch.' => 'dispatch',
        'admin.settings.' => 'settings',
        'admin.site-content.' => 'content',
        'admin.testimonials.' => 'content',
        'admin.reports.overview' => 'reports.profit',
        'admin.reports.sales' => 'reports.profit',
        'admin.reports.compliance' => 'reports.profit',
        'admin.reports.payouts' => 'reports.payouts',
        'admin.reports.levels' => 'reports.levels',
        'admin.reports.pending' => 'reports.pending',
        'admin.reports.repurchase' => 'reports.repurchase',
    ];

    /** Pages every signed-in staff member can reach. */
    private const ALWAYS = ['admin.dashboard', 'admin.logout'];

    /** @return list<string> */
    public static function keys(): array
    {
        return collect(self::GROUPS)->flatMap(fn ($items) => array_keys($items))->values()->all();
    }

    /**
     * The permission a route needs: null = open to all staff, 'super' = Super Admin only.
     * A route under admin.* that isn't mapped is treated as Super Admin only (fail closed).
     */
    public static function forRoute(?string $name): ?string
    {
        if ($name === null || ! str_starts_with($name, 'admin.') || in_array($name, self::ALWAYS, true)) {
            return null;
        }

        foreach (self::ROUTE_MAP as $prefix => $permission) {
            if (str_starts_with($name, $prefix)) {
                return $permission;
            }
        }

        return 'super';
    }
}
