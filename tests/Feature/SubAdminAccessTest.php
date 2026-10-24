<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
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

    private function product(array $over = []): Product
    {
        $cat = Category::firstOrCreate(['slug' => 'wellness'], ['name' => 'Wellness', 'is_free' => false]);

        return Product::create($over + [
            'category_id' => $cat->id, 'name' => 'Immunity Syrup', 'sku' => 'SYR-'.random_int(1, 99999),
            'cost_price' => 150, 'sell_price' => 300, 'gst_pct' => 5, 'is_active' => true,
        ]);
    }

    // --------------------------------------------------------------- route access ---------------------------------------------------------------

    public function test_a_sub_admin_with_no_permissions_sees_only_the_dashboard(): void
    {
        $sub = $this->sub();

        $this->as($sub)->get(route('admin.dashboard'))->assertOk();
        foreach (['admin.orders.index', 'admin.members.index', 'admin.settings.edit', 'admin.reports.overview', 'admin.reports.payouts', 'admin.cycles.index', 'admin.team.index', 'admin.products.index'] as $route) {
            $this->as($sub)->get(route($route))->assertForbidden();
        }
    }

    public function test_every_profit_and_money_flow_screen_is_closed_without_finance_access(): void
    {
        // Even someone trusted with every other switch can't see where the profit is or how money goes.
        $everythingButFinance = array_values(array_diff(AdminPermissions::keys(), ['finance']));
        $sub = $this->sub($everythingButFinance);

        foreach ([
            'admin.reports.overview', 'admin.reports.sales', 'admin.reports.compliance', 'admin.reports.payouts', 'admin.reports.levels',
            'admin.reports.sales.csv', 'admin.reports.payouts.csv', 'admin.reports.levels.csv', 'admin.reports.overview.trend.csv',
            'admin.settings.edit',
        ] as $route) {
            $this->as($sub)->get(route($route))->assertForbidden();
        }
        $this->as($sub)->put(route('admin.settings.update'), [])->assertForbidden();
        $this->as($sub)->put(route('admin.settings.ranks'), [])->assertForbidden();
    }

    public function test_the_master_can_grant_finance_access_to_open_them(): void
    {
        $sub = $this->sub(['finance']);

        foreach (['admin.reports.overview', 'admin.reports.sales', 'admin.reports.compliance', 'admin.reports.payouts', 'admin.reports.levels', 'admin.settings.edit'] as $route) {
            $this->as($sub)->get(route($route))->assertOk();
        }
        // Finance doesn't unlock unrelated areas.
        $this->as($sub)->get(route('admin.orders.index'))->assertForbidden();
        $this->as($sub)->get(route('admin.team.index'))->assertForbidden();
    }

    public function test_operational_reports_can_be_granted_without_finance(): void
    {
        $sub = $this->sub(['reports.pending', 'reports.repurchase']);

        $this->as($sub)->get(route('admin.reports.pending'))->assertOk();
        $this->as($sub)->get(route('admin.reports.repurchase'))->assertOk();
        $this->as($sub)->get(route('admin.reports.sales'))->assertForbidden();
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
        $this->as($sub)->post(route('admin.team.store'), [])->assertForbidden();
    }

    public function test_the_super_admin_can_open_everything(): void
    {
        foreach (['admin.dashboard', 'admin.settings.edit', 'admin.team.index', 'admin.reports.overview', 'admin.reports.sales', 'admin.orders.index', 'admin.products.index', 'admin.plans.index'] as $route) {
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
        $this->assertSame(['super'], AdminPermissions::requirementsFor('admin.brand-new-page.index'));
        $this->assertSame([], AdminPermissions::requirementsFor('admin.dashboard'));
        $this->assertSame(['finance'], AdminPermissions::requirementsFor('admin.reports.sales.purchases.csv'));
    }

    // ------------------------------------------------------------ catalog: view only ----------------------------------------------------------

    public function test_the_catalog_is_view_only_for_a_sub_admin(): void
    {
        $sub = $this->sub(['catalog']);
        $product = $this->product();

        foreach (['admin.products.index', 'admin.categories.index', 'admin.plans.index'] as $route) {
            $this->as($sub)->get(route($route))->assertOk();
        }

        $this->as($sub)->get(route('admin.products.create'))->assertForbidden();
        $this->as($sub)->post(route('admin.products.store'), [])->assertForbidden();
        $this->as($sub)->get(route('admin.products.edit', $product))->assertForbidden();
        $this->as($sub)->put(route('admin.products.update', $product), ['name' => 'Hacked'])->assertForbidden();
        $this->as($sub)->delete(route('admin.products.destroy', $product))->assertForbidden();
        $this->as($sub)->get(route('admin.categories.create'))->assertForbidden();
        $this->as($sub)->get(route('admin.plans.create'))->assertForbidden();

        $this->assertSame('Immunity Syrup', $product->fresh()->name);
    }

    public function test_the_catalog_list_has_no_edit_buttons_for_a_view_only_sub_admin(): void
    {
        $this->product();

        $this->as($this->sub(['catalog']))->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Immunity Syrup')
            ->assertDontSee('New product')
            ->assertDontSee('>Edit<', false);

        $this->as($this->super)->get(route('admin.products.index'))->assertSee('New product');
    }

    public function test_the_master_can_let_someone_edit_the_catalog(): void
    {
        $sub = $this->sub(['catalog.manage']);
        $product = $this->product();

        // Editing implies viewing.
        $this->as($sub)->get(route('admin.products.index'))->assertOk();
        $this->as($sub)->get(route('admin.products.edit', $product))->assertOk();
        $this->as($sub)->get(route('admin.categories.create'))->assertOk();
    }

    public function test_plans_are_part_of_the_money_model_so_editing_them_also_needs_finance(): void
    {
        $this->as($this->sub(['catalog.manage']))->get(route('admin.plans.create'))->assertForbidden();
        $this->as($this->sub(['catalog.manage', 'finance']))->get(route('admin.plans.create'))->assertOk();
    }

    // ----------------------------------------------------- finance figures stay hidden -----------------------------------------------------

    public function test_cost_prices_and_gst_never_show_in_the_product_list_without_finance(): void
    {
        $this->product(['name' => 'Costly Item', 'cost_price' => 4321]);

        $html = $this->as($this->sub(['catalog']))->get(route('admin.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Costly Item', $html);
        $this->assertStringNotContainsString('Cost Price', $html);
        $this->assertStringNotContainsString('4,321', $html);

        $this->as($this->super)->get(route('admin.products.index'))->assertSee('4,321');
    }

    public function test_the_plan_list_for_a_sub_admin_has_no_cost_bv_or_payout_figures(): void
    {
        $plan = Plan::create(['name' => 'Essential', 'slug' => 'essential', 'price' => 2000, 'bv' => 800, 'is_active' => true]);
        $plan->products()->attach($this->product(['cost_price' => 777])->id, ['qty' => 2]);

        $html = $this->as($this->sub(['catalog']))->get(route('admin.plans.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Essential', $html);
        $this->assertStringContainsString('2,000', $html);
        foreach (['Bundle Cost', 'Self (', 'Sponsor (', 'Cascade', 'Compensation Matrix', 'BV payouts', 'target split'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->as($this->super)->get(route('admin.plans.index'))->assertSee('Compensation Matrix');
    }

    public function test_a_catalog_editor_without_finance_cannot_set_or_change_cost_or_gst(): void
    {
        $sub = $this->sub(['catalog.manage']);
        $product = $this->product(['cost_price' => 150, 'gst_pct' => 5]);

        // The form simply doesn't offer them...
        $this->as($sub)->get(route('admin.products.edit', $product))->assertOk()->assertDontSee('name="cost_price"', false)->assertDontSee('name="gst_pct"', false);

        // ...and posting them by hand changes nothing.
        $this->as($sub)->put(route('admin.products.update', $product), [
            'category_id' => $product->category_id, 'name' => 'Renamed Syrup', 'sku' => $product->sku,
            'sell_price' => 320, 'cost_price' => 1, 'gst_pct' => 28, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Renamed Syrup', $product->name);
        $this->assertEquals(320, (float) $product->sell_price);
        $this->assertEquals(150, (float) $product->cost_price);
        $this->assertEquals(5, (float) $product->gst_pct);
    }

    public function test_a_catalog_editor_can_add_a_product_and_its_cost_starts_at_zero(): void
    {
        $sub = $this->sub(['catalog.manage']);
        $cat = Category::create(['name' => 'Tea', 'slug' => 'tea', 'is_free' => false]);

        $this->as($sub)->post(route('admin.products.store'), [
            'category_id' => $cat->id, 'name' => 'New Tea', 'sku' => 'TEA-NEW', 'sell_price' => 150, 'cost_price' => 99,
        ])->assertRedirect(route('admin.products.index'));

        $new = Product::where('sku', 'TEA-NEW')->firstOrFail();
        $this->assertEquals(0, (float) $new->cost_price);
        $this->assertNull($new->gst_pct);
    }

    public function test_finance_access_shows_and_saves_cost_and_gst(): void
    {
        $sub = $this->sub(['catalog.manage', 'finance']);
        $product = $this->product(['cost_price' => 150]);

        $this->as($sub)->get(route('admin.products.edit', $product))->assertSee('name="cost_price"', false);

        $this->as($sub)->put(route('admin.products.update', $product), [
            'category_id' => $product->category_id, 'name' => $product->name, 'sku' => $product->sku,
            'sell_price' => 300, 'cost_price' => 175, 'gst_pct' => 18, 'is_active' => 1,
        ]);

        $this->assertEquals(175, (float) $product->fresh()->cost_price);
        $this->assertEquals(18, (float) $product->fresh()->gst_pct);
    }

    public function test_the_dashboard_hides_commission_totals_and_finance_shortcuts_without_finance(): void
    {
        $this->as($this->sub(['orders']))->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('Commissions Posted Today')
            ->assertDontSee('Commissions This Month')
            ->assertDontSee('Compensation Rules')
            ->assertDontSee('Level-Wise Distribution');

        $this->as($this->super)->get(route('admin.dashboard'))->assertSee('Commissions This Month')->assertSee('Compensation Rules');
    }

    public function test_the_cycles_page_hides_pool_and_paid_out_totals_without_finance(): void
    {
        Cycle::create(['period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(), 'status' => 'approved', 'pool_bv' => 123456, 'total_paid' => 98765]);

        $html = $this->as($this->sub(['cycles']))->get(route('admin.cycles.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('123,456', $html);
        $this->assertStringNotContainsString('98,765', $html);
        $this->assertStringNotContainsString('Total Paid Out', $html);

        $this->as($this->super)->get(route('admin.cycles.index'))->assertSee('123,456')->assertSee('98,765');
    }

    public function test_approving_a_cycle_does_not_reveal_the_totals_to_a_sub_admin(): void
    {
        $cycle = Cycle::create(['period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(), 'status' => 'draft']);

        $this->as($this->sub(['cycles']))->put(route('admin.cycles.approve', $cycle))
            ->assertSessionHas('status', 'Cycle approved — income has been posted to members.');
    }

    public function test_the_operational_reports_hide_bv_and_gst_without_finance(): void
    {
        $sub = $this->sub(['reports.pending', 'reports.repurchase']);

        $this->as($sub)->get(route('admin.reports.repurchase'))->assertOk()->assertDontSee('Repurchase BV')->assertDontSee('>GST<', false);
        $this->as($sub)->get(route('admin.reports.pending'))->assertOk()->assertDontSee('>BV<', false);

        $this->as($this->super)->get(route('admin.reports.repurchase'))->assertSee('Repurchase BV');
    }

    public function test_the_orders_page_hides_gst_without_finance(): void
    {
        $plan = Plan::create(['name' => 'Essential', 'slug' => 'essential', 'price' => 2000, 'bv' => 800, 'is_active' => true]);
        $member = Member::create(['member_code' => 'BSORD001', 'name' => 'Buyer', 'phone' => '9000000010', 'status' => 'active']);
        Order::create(['order_code' => 'ORD-X1', 'member_id' => $member->id, 'plan_id' => $plan->id, 'amount' => 2240, 'taxable_amount' => 2000, 'gst_amount' => 240, 'bv' => 800, 'status' => 'paid']);

        $this->as($this->sub(['orders']))->get(route('admin.orders.index'))->assertOk()->assertSee('ORD-X1')->assertDontSee('GST ₹');
        $this->as($this->super)->get(route('admin.orders.index'))->assertSee('GST ₹240');
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
        $html = $this->as($this->sub(['reports.pending']))->get(route('admin.dashboard'))->getContent();

        $this->assertStringContainsString(route('admin.reports.pending'), $html);
        $this->assertStringNotContainsString(route('admin.reports.overview'), $html);
    }

    public function test_report_tabs_hide_the_reports_they_cannot_open(): void
    {
        $this->as($this->sub(['reports.pending']))->get(route('admin.reports.pending'))
            ->assertOk()
            ->assertSee('Pending & cash')
            ->assertDontSee('Business overview')
            ->assertDontSee('Compliance / audit')
            ->assertDontSee('Level-wise income');
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

    public function test_the_super_admin_can_generate_a_new_password_and_sees_it_once(): void
    {
        $sub = $this->sub(['orders']);
        $oldHash = $sub->password;

        $response = $this->as($this->super)->put(route('admin.team.password', $sub))->assertRedirect(route('admin.team.edit', $sub));
        $new = session('new_password');

        $this->assertSame($sub->email, $new['email']);
        $this->assertGreaterThanOrEqual(12, strlen($new['password']));
        $this->assertNotSame($oldHash, $sub->fresh()->password);
        $this->assertTrue(\Hash::check($new['password'], $sub->fresh()->password));
        // The plain text is never stored anywhere.
        $this->assertStringNotContainsString($new['password'], (string) $sub->fresh()->password);

        $this->as($this->super)->get(route('admin.team.edit', $sub))->assertSee($new['password']);
        // Gone on the next visit.
        $this->as($this->super)->get(route('admin.team.edit', $sub))->assertDontSee($new['password']);
    }

    public function test_the_new_password_works_and_the_old_one_stops(): void
    {
        $sub = $this->sub(['orders']);
        $this->as($this->super)->put(route('admin.team.password', $sub));
        $new = session('new_password')['password'];
        auth()->guard('web')->logout();

        $this->post(route('admin.login.submit'), ['email' => $sub->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->post(route('admin.login.submit'), ['email' => $sub->email, 'password' => $new])->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_sub_admin_cannot_reset_anyones_password_and_super_admins_are_untouchable(): void
    {
        $sub = $this->sub(['orders']);
        $other = $this->sub(['orders']);

        $this->as($sub)->put(route('admin.team.password', $other))->assertForbidden();
        $this->as($this->super)->put(route('admin.team.password', $this->super))->assertNotFound();
    }

    public function test_the_super_admin_can_remove_a_sub_admin(): void
    {
        $sub = $this->sub(['orders']);

        $this->as($this->super)->delete(route('admin.team.destroy', $sub))->assertRedirect(route('admin.team.index'));

        $this->assertNull(User::find($sub->id));
    }

    public function test_the_team_pages_render_for_the_super_admin(): void
    {
        $sub = $this->sub(['finance']);

        $this->as($this->super)->get(route('admin.team.index'))->assertOk()->assertSee('Sub Admin')->assertSee('Finance & money flow');
        $this->as($this->super)->get(route('admin.team.create'))->assertOk()->assertSee('What they can see')->assertSee('Catalog — view only')->assertSee('Catalog — add & edit');
        $this->as($this->super)->get(route('admin.team.edit', $sub))->assertOk()->assertSee('value="finance"', false);
    }
}
