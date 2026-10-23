<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = User::create(['name' => 'Super', 'email' => 'super@t.com', 'password' => 'secret123', 'role' => 'admin']);
        Setting::set('gst_pct', 12);
    }

    private function sub(array $permissions = [], bool $active = true): User
    {
        return User::create([
            'name' => 'Sub Admin', 'email' => 'sub'.random_int(1, 99999).'@t.com', 'password' => 'secret123',
            'role' => 'sub_admin', 'permissions' => $permissions, 'is_active' => $active,
        ]);
    }

    private function as(User $u)
    {
        return $this->actingAs($u, 'web');
    }

    // --------------------------------------------------------------- route access ---------------------------------------------------------------

    public function test_a_sub_admin_with_no_permissions_sees_only_the_dashboard(): void
    {
        $sub = $this->sub();

        $this->as($sub)->get(route('admin.dashboard'))->assertOk();
        foreach (['admin.orders.index', 'admin.members.index', 'admin.settings.edit', 'admin.reports.overview', 'admin.reports.payouts', 'admin.cycles.index', 'admin.team.index'] as $route) {
            $this->as($sub)->get(route($route))->assertForbidden();
        }
    }

    public function test_profit_reports_are_closed_until_the_super_admin_grants_them(): void
    {
        $sub = $this->sub(['reports.payouts']);

        $this->as($sub)->get(route('admin.reports.payouts'))->assertOk();
        foreach (['admin.reports.overview', 'admin.reports.sales', 'admin.reports.compliance', 'admin.reports.sales.csv', 'admin.reports.overview.trend.csv'] as $route) {
            $this->as($sub)->get(route($route))->assertForbidden();
        }

        $withProfit = $this->sub(['reports.profit']);
        foreach (['admin.reports.overview', 'admin.reports.sales', 'admin.reports.compliance'] as $route) {
            $this->as($withProfit)->get(route($route))->assertOk();
        }
        $this->as($withProfit)->get(route('admin.reports.payouts'))->assertForbidden();
    }

    public function test_granting_a_permission_opens_exactly_that_section(): void
    {
        $sub = $this->sub(['orders']);

        $this->as($sub)->get(route('admin.orders.index'))->assertOk();
        $this->as($sub)->get(route('admin.payments.index'))->assertForbidden();
        $this->as($sub)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_actions_are_protected_too_not_just_pages(): void
    {
        $sub = $this->sub(['orders']);

        $this->as($sub)->put(route('admin.settings.update'), [])->assertForbidden();
        $this->as($sub)->put(route('admin.settings.ranks'), [])->assertForbidden();
        $this->as($sub)->post(route('admin.team.store'), [])->assertForbidden();
    }

    public function test_the_super_admin_can_open_everything(): void
    {
        foreach (['admin.dashboard', 'admin.settings.edit', 'admin.team.index', 'admin.reports.overview', 'admin.reports.sales', 'admin.orders.index'] as $route) {
            $this->as($this->super)->get(route($route))->assertOk();
        }
    }

    public function test_a_switched_off_sub_admin_is_locked_out_of_the_console(): void
    {
        $this->as($this->sub(['orders'], false))->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_a_switched_off_sub_admin_cannot_log_in(): void
    {
        $sub = $this->sub(['orders'], false);

        $this->post(route('admin.login.submit'), ['email' => $sub->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
    }

    public function test_a_sub_admin_signs_in_through_the_normal_admin_login(): void
    {
        $sub = $this->sub(['orders']);

        $this->post(route('admin.login.submit'), ['email' => $sub->email, 'password' => 'secret123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_an_unmapped_admin_route_is_closed_to_sub_admins_by_default(): void
    {
        $this->assertSame('super', AdminPermissions::forRoute('admin.brand-new-page.index'));
        $this->assertNull(AdminPermissions::forRoute('admin.dashboard'));
        $this->assertSame('reports.profit', AdminPermissions::forRoute('admin.reports.sales.purchases.csv'));
    }

    // ------------------------------------------------------------------ what they see ----------------------------------------------------------

    public function test_the_sidebar_only_lists_what_the_sub_admin_can_open(): void
    {
        $html = $this->as($this->sub(['orders']))->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Orders', $html);
        $this->assertStringNotContainsString(route('admin.settings.edit'), $html);
        $this->assertStringNotContainsString(route('admin.team.index'), $html);
        $this->assertStringNotContainsString(route('admin.cycles.index'), $html);
    }

    public function test_the_super_admin_sees_the_sub_admin_link(): void
    {
        $this->as($this->super)->get(route('admin.dashboard'))->assertSee(route('admin.team.index'), false);
    }

    public function test_the_reports_link_opens_the_first_report_they_are_allowed(): void
    {
        $html = $this->as($this->sub(['reports.levels']))->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString(route('admin.reports.levels'), $html);
        $this->assertStringNotContainsString(route('admin.reports.overview'), $html);
    }

    public function test_report_tabs_hide_the_reports_they_cannot_open(): void
    {
        $this->as($this->sub(['reports.payouts']))->get(route('admin.reports.payouts'))
            ->assertOk()
            ->assertSee('Payouts')
            ->assertDontSee('Business overview')
            ->assertDontSee('Compliance / audit');
    }

    public function test_a_sub_admin_is_shown_as_an_administrator_not_a_sub_admin(): void
    {
        $this->as($this->sub())->get(route('admin.dashboard'))->assertSee('Administrator')->assertDontSee('Sub-admin');
    }

    // --------------------------------------------------------------- managing sub-admins --------------------------------------------------------

    public function test_the_super_admin_creates_a_sub_admin_and_picks_what_they_see(): void
    {
        $this->as($this->super)->post(route('admin.team.store'), [
            'name' => 'New Helper', 'email' => 'helper@t.com', 'password' => 'helper-pass-1',
            'permissions' => ['orders', 'members'], 'is_active' => 1,
        ])->assertRedirect(route('admin.team.index'));

        $helper = User::where('email', 'helper@t.com')->firstOrFail();
        $this->assertSame('sub_admin', $helper->role);
        $this->assertEqualsCanonicalizing(['orders', 'members'], $helper->permissions);
        $this->assertNotSame('helper-pass-1', $helper->password);
        $this->assertTrue($helper->is_active);
    }

    public function test_unknown_permission_keys_are_rejected(): void
    {
        $this->as($this->super)->post(route('admin.team.store'), [
            'name' => 'X', 'email' => 'x@t.com', 'password' => 'password-1', 'permissions' => ['orders', 'super'],
        ])->assertSessionHasErrors('permissions.1');

        $this->assertNull(User::where('email', 'x@t.com')->first());
    }

    public function test_a_password_is_required_for_a_new_sub_admin_but_optional_when_editing(): void
    {
        $this->as($this->super)->post(route('admin.team.store'), ['name' => 'X', 'email' => 'x2@t.com'])
            ->assertSessionHasErrors('password');

        $sub = $this->sub(['orders']);
        $old = $sub->password;

        $this->as($this->super)->put(route('admin.team.update', $sub), [
            'name' => 'Renamed', 'email' => $sub->email, 'password' => '', 'permissions' => ['orders', 'payments'], 'is_active' => 1,
        ])->assertRedirect(route('admin.team.index'));

        $sub->refresh();
        $this->assertSame('Renamed', $sub->name);
        $this->assertSame($old, $sub->password);
        $this->assertEqualsCanonicalizing(['orders', 'payments'], $sub->permissions);
    }

    public function test_access_changes_take_effect_immediately(): void
    {
        $sub = $this->sub(['orders']);
        $this->as($sub)->get(route('admin.payments.index'))->assertForbidden();

        $this->as($this->super)->put(route('admin.team.update', $sub), [
            'name' => $sub->name, 'email' => $sub->email, 'permissions' => ['orders', 'payments'], 'is_active' => 1,
        ]);

        $this->as($sub->fresh())->get(route('admin.payments.index'))->assertOk();
    }

    public function test_a_super_admin_account_cannot_be_edited_or_removed_from_the_sub_admin_screen(): void
    {
        $other = User::create(['name' => 'Other Super', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'admin']);

        $this->as($this->super)->get(route('admin.team.edit', $other))->assertNotFound();
        $this->as($this->super)->delete(route('admin.team.destroy', $other))->assertNotFound();
        $this->assertNotNull($other->fresh());
    }

    public function test_the_super_admin_can_remove_a_sub_admin(): void
    {
        $sub = $this->sub(['orders']);

        $this->as($this->super)->delete(route('admin.team.destroy', $sub))->assertRedirect(route('admin.team.index'));

        $this->assertNull(User::find($sub->id));
    }

    public function test_the_team_pages_render_for_the_super_admin(): void
    {
        $sub = $this->sub(['reports.profit']);

        $this->as($this->super)->get(route('admin.team.index'))->assertOk()->assertSee('Sub Admin')->assertSee('Profit reports');
        $this->as($this->super)->get(route('admin.team.create'))->assertOk()->assertSee('What they can see');
        $this->as($this->super)->get(route('admin.team.edit', $sub))->assertOk()->assertSee('value="reports.profit"', false);
    }
}
