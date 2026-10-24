<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionAccessAndProfitTest extends TestCase
{
    use RefreshDatabase;

    private Region $gj;

    private Region $mh;

    private Plan $gjPlan;

    private Plan $mhPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ', 'sort_order' => 1]);
        $this->mh = Region::create(['name' => 'Maharashtra', 'code' => 'MH', 'sort_order' => 2]);

        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->gjPlan = Plan::create(['name' => 'GJ Plan', 'slug' => 'gj', 'price' => 10000, 'bv' => 4000, 'is_active' => true, 'region_id' => $this->gj->id]);
        $this->mhPlan = Plan::create(['name' => 'MH Plan', 'slug' => 'mh', 'price' => 5000, 'bv' => 2000, 'is_active' => true, 'region_id' => $this->mh->id]);
        $this->gjPlan->products()->attach($product->id, ['qty' => 1]);
        $this->mhPlan->products()->attach($product->id, ['qty' => 2]);
    }

    private function member(string $code, Region $r, ?Member $parent = null, string $pos = 'left'): Member
    {
        return Member::create([
            'member_code' => $code, 'name' => $code, 'phone' => '9'.random_int(100000000, 999999999), 'region_id' => $r->id,
            'sponsor_id' => $parent?->id, 'placement_id' => $parent?->id, 'position' => $parent ? $pos : null,
            'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null, 'status' => 'active',
        ]);
    }

    private function sale(Member $m, Plan $p, float $income = 0): Order
    {
        $o = Order::create([
            'order_code' => 'ORD'.random_int(1000, 9999999), 'member_id' => $m->id, 'plan_id' => $p->id,
            'amount' => $p->price * 1.12, 'taxable_amount' => $p->price, 'gst_amount' => $p->price * 0.12, 'bv' => $p->bv, 'status' => 'settled',
        ]);
        if ($income > 0) {
            LedgerEntry::create(['member_id' => $m->id, 'order_id' => $o->id, 'type' => 'self', 'amount' => $income, 'description' => 'self']);
        }

        return $o;
    }

    private function subAdmin(array $perms, ?array $regions): User
    {
        return User::create(['name' => 'Broker', 'email' => 'b'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function range(): array
    {
        return [now()->startOfMonth()->subDay(), now()->endOfMonth()->addDay()];
    }

    // ------------------------------------------------------------ profit & loss ----------------------------------------------------

    public function test_profit_is_sales_minus_product_cost_minus_member_income(): void
    {
        $this->sale($this->member('G1', $this->gj), $this->gjPlan, 1200); // sales 10000, cost 100, income 1200

        [$from, $to] = $this->range();
        $d = (new ReportService)->businessOverview($from, $to);

        $this->assertEquals(10000, $d['taxable_revenue']);
        $this->assertEquals(100, $d['cost_of_goods']);
        $this->assertEquals(9900, $d['gross_profit']);
        $this->assertEquals(1200, $d['paid_out']);
        $this->assertEquals(8700, $d['net_profit']);
        $this->assertEquals(87.0, $d['net_margin_pct']);
    }

    public function test_a_loss_is_shown_as_a_loss(): void
    {
        $this->sale($this->member('G1', $this->gj), $this->gjPlan, 12000); // pays out more than it took in
        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'web')->get(route('admin.reports.overview'))
            ->assertOk()->assertSee('Loss')->assertSee('−₹2,100');
    }

    public function test_region_profit_rows_add_up_to_the_company_profit(): void
    {
        $this->sale($this->member('G1', $this->gj), $this->gjPlan, 1200);
        $this->sale($this->member('M1', $this->mh), $this->mhPlan, 500); // sales 5000, cost 200, income 500

        [$from, $to] = $this->range();
        $rows = (new ReportService)->regionBreakdown($from, $to);
        $all = (new ReportService)->businessOverview($from, $to);

        $this->assertEquals(8700, $rows->firstWhere(fn ($r) => $r['region']->id === $this->gj->id)['profit']);
        $this->assertEquals(4300, $rows->firstWhere(fn ($r) => $r['region']->id === $this->mh->id)['profit']);
        $this->assertEquals($all['net_profit'], $rows->sum('profit'));
    }

    // ------------------------------------------------------------ 7 levels --------------------------------------------------------

    public function test_the_team_is_laid_out_by_level_with_all_seven_shown(): void
    {
        $chain = [$this->member('L1', $this->gj)];
        for ($i = 2; $i <= 9; $i++) {
            $chain[] = $this->member("L$i", $this->gj, $chain[$i - 2]);
        }
        $this->sale($chain[2], $this->gjPlan, 100);

        [$from, $to] = $this->range();
        $net = (new ReportService)->forRegion($this->gj->id)->networkByLevel($from, $to);

        $this->assertSame(['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L7+ (deeper)'], $net['rows']->pluck('label')->all());
        $this->assertSame([1, 1, 1, 1, 1, 1, 1, 2], $net['rows']->pluck('members')->all(), 'Levels 8 and 9 fall into the deeper row');
        $this->assertEquals(10000, $net['rows'][2]['taxable']);
        $this->assertEquals(100, $net['rows'][2]['income']);
        $this->assertSame(9, $net['total']['members']);
    }

    public function test_an_empty_tree_still_shows_all_seven_levels(): void
    {
        [$from, $to] = $this->range();
        $net = (new ReportService)->forRegion($this->mh->id)->networkByLevel($from, $to);

        $this->assertCount(7, $net['rows']);
        $this->assertSame(0, $net['total']['members']);

        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'web')->get(route('admin.reports.levels'))->assertOk()->assertSee('Team by level')->assertSee('L7');
    }

    // ------------------------------------------------------------ region access for sub-admins -------------------------------------

    public function test_a_region_limited_sub_admin_sees_only_their_regions_everywhere(): void
    {
        $g = $this->member('GJMEMBER', $this->gj);
        $m = $this->member('MHMEMBER', $this->mh);
        $this->sale($g, $this->gjPlan, 100);
        $this->sale($m, $this->mhPlan, 50);
        $sub = $this->subAdmin(['members', 'orders', 'regions', 'catalog', 'finance'], [$this->gj->id]);

        $this->actingAs($sub, 'web')->get(route('admin.members.index'))->assertSee('GJMEMBER')->assertDontSee('MHMEMBER');
        $this->actingAs($sub, 'web')->get(route('admin.orders.index'))->assertOk()->assertSee('GJMEMBER')->assertDontSee('MHMEMBER');
        $this->actingAs($sub, 'web')->get(route('admin.regions.index'))->assertSee('Gujarat')->assertDontSee('Maharashtra');
        $this->actingAs($sub, 'web')->get(route('admin.plans.index'))->assertSee('GJ Plan')->assertDontSee('MH Plan');
        // Another region's member is not just hidden from lists: opening them directly is a 404.
        $this->actingAs($sub, 'web')->get(route('admin.members.show', $m))->assertNotFound();
    }

    public function test_reports_for_a_region_limited_sub_admin_count_only_their_regions(): void
    {
        $this->sale($this->member('G1', $this->gj), $this->gjPlan, 1200);
        $this->sale($this->member('M1', $this->mh), $this->mhPlan, 500);
        $sub = $this->subAdmin(['finance'], [$this->gj->id]);

        $res = $this->actingAs($sub, 'web')->get(route('admin.reports.overview'))->assertOk();
        $this->assertSame(1, $res->viewData('data')['order_count']);
        $this->assertEquals(8700, $res->viewData('data')['net_profit']);
        $res->assertDontSee('Maharashtra');

        // Asking for the other region by hand shows nothing of it.
        $other = $this->actingAs($sub, 'web')->get(route('admin.reports.overview', ['region' => $this->mh->id]))->assertOk();
        $this->assertSame(0, $other->viewData('data')['order_count']);
    }

    public function test_a_sub_admin_with_two_regions_sees_both_and_not_the_third(): void
    {
        $dl = Region::create(['name' => 'Delhi', 'code' => 'DL', 'sort_order' => 3]);
        $this->member('GJMEMBER', $this->gj);
        $this->member('MHMEMBER', $this->mh);
        $this->member('DLMEMBER', $dl);
        $sub = $this->subAdmin(['members'], [$this->gj->id, $this->mh->id]);

        $this->actingAs($sub, 'web')->get(route('admin.members.index'))
            ->assertSee('GJMEMBER')->assertSee('MHMEMBER')->assertDontSee('DLMEMBER');
    }

    public function test_an_unrestricted_sub_admin_and_the_super_admin_see_every_region(): void
    {
        $this->member('GJMEMBER', $this->gj);
        $this->member('MHMEMBER', $this->mh);

        $this->actingAs($this->subAdmin(['members'], null), 'web')->get(route('admin.members.index'))->assertSee('GJMEMBER')->assertSee('MHMEMBER');
    }

    public function test_region_limited_people_cannot_run_actions_that_touch_every_region(): void
    {
        $sub = $this->subAdmin(['cycles', 'dispatch', 'regions'], [$this->gj->id]);

        $this->actingAs($sub, 'web')->post(route('admin.cycles.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertForbidden();
        $this->actingAs($sub, 'web')->post(route('admin.regions.store'), ['name' => 'New', 'code' => 'NEW'])->assertForbidden();
        $this->actingAs($sub, 'web')->get(route('admin.cycles.index'))->assertOk();
    }

    public function test_plan_forms_cannot_target_a_region_the_person_cannot_see(): void
    {
        $sub = $this->subAdmin(['catalog.manage', 'finance'], [$this->gj->id]);

        $this->actingAs($sub, 'web')->post(route('admin.plans.store'), ['region_id' => $this->mh->id, 'name' => 'Sneaky', 'price' => 1000, 'bv' => 100])->assertForbidden();
        $this->assertDatabaseMissing('plans', ['name' => 'Sneaky']);
    }

    public function test_team_form_saves_region_access(): void
    {
        $super = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($super, 'web')->post(route('admin.team.store'), [
            'name' => 'Broker', 'email' => 'broker@t.com', 'password' => 'secret1234', 'is_active' => 1,
            'permissions' => ['members'], 'region_access' => 'selected', 'region_ids' => [$this->mh->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$this->mh->id], User::where('email', 'broker@t.com')->first()->region_ids);

        $this->actingAs($super, 'web')->post(route('admin.team.store'), [
            'name' => 'Empty', 'email' => 'empty@t.com', 'password' => 'secret1234', 'region_access' => 'selected',
        ])->assertSessionHasErrors('region_ids');

        $this->actingAs($super, 'web')->get(route('admin.team.index'))->assertOk()->assertSee('Maharashtra');
    }
}
