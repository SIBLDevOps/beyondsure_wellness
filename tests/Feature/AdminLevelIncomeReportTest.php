<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CompensationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLevelIncomeReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        Setting::set('split_cost_pct', 40);
        Setting::set('split_pool_pct', 40);
        Setting::set('split_company_pct', 20);
        Setting::set('self_pct', 10);
        Setting::set('sponsor_pct', 20);
        Setting::set('matching_pct', 10);
        Setting::set('cascade_depth', 7);
        Setting::set('rank_pool_pct', 3);
        Setting::set('match_cap_per_cycle', 600000);
        Setting::set('match_cap_per_week', 150000);

        $category = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Syrup',
            'sku' => 'SYR-1',
            'cost_price' => 100,
            'sell_price' => 200,
            'is_active' => true,
        ]);

        $this->plan = Plan::create([
            'name' => 'Essential Bundle',
            'slug' => 'essential-bundle',
            'price' => 2000,
            'bv' => 800,
            'is_active' => true,
        ]);

        $this->plan->products()->attach($product->id, ['qty' => 1]);
    }

    public function test_guest_cannot_access_level_income_report(): void
    {
        $response = $this->get(route('admin.reports.levels'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_level_income_distribution_report(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.reports.levels'));

        $response->assertStatus(200);
        $response->assertSee('Level-wise income distribution');
        $response->assertSee('How Level-Wise Income is Distributed');
        $response->assertSee('Cascade: 70% (7 Levels)');
        $response->assertSee('Level 1 (Direct Referral & 1st Match)');
        $response->assertSee('Essential Bundle');
        $response->assertSee('₹800'); // BV
    }

    public function test_admin_can_export_level_income_csv(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.reports.levels.csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_view_member_level_illustration_with_income(): void
    {
        $root = Member::create([
            'member_code' => 'BSROOT01',
            'name' => 'Root Member',
            'phone' => '9999999991',
            'status' => 'active',
        ]);

        $child1 = Member::create([
            'member_code' => 'BSCHLD01',
            'name' => 'Child Level 1',
            'phone' => '9999999992',
            'sponsor_id' => $root->id,
            'placement_id' => $root->id,
            'position' => 'left',
            'path' => $root->id.'.',
            'status' => 'active',
        ]);

        $child2 = Member::create([
            'member_code' => 'BSCHLD02',
            'name' => 'Child Level 2',
            'phone' => '9999999993',
            'sponsor_id' => $child1->id,
            'placement_id' => $child1->id,
            'position' => 'left',
            'path' => $root->id.'.'.$child1->id.'.',
            'status' => 'active',
        ]);

        $cycle = Cycle::create([
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'status' => 'approved',
            'pool_bv' => 800,
            'total_paid' => 240,
        ]);

        $order = Order::create([
            'order_code' => 'ORD-TEST01',
            'member_id' => $child1->id,
            'plan_id' => $this->plan->id,
            'amount' => 2000,
            'bv' => 800,
            'status' => 'settled',
            'cycle_id' => $cycle->id,
        ]);

        // Sponsor income paid to root
        LedgerEntry::create([
            'member_id' => $root->id,
            'source_member_id' => $child1->id,
            'order_id' => $order->id,
            'cycle_id' => $cycle->id,
            'type' => 'sponsor',
            'amount' => 160.00,
            'description' => 'Sponsor income on order ORD-TEST01',
        ]);

        // Matching cascade paid to root from level 2
        LedgerEntry::create([
            'member_id' => $root->id,
            'source_member_id' => $child2->id,
            'cycle_id' => $cycle->id,
            'type' => 'matching',
            'amount' => 80.00,
            'description' => 'Matching cascade (Level 2) from BSCHLD02',
        ]);

        $response = $this->actingAs($this->admin, 'web')->get(route('admin.members.levels', $root));

        $response->assertStatus(200);
        $response->assertSee('Level-wise income — Root Member');
        $response->assertSee('Total level income earned');
        $response->assertSee('₹240.00'); // 160 + 80
        $response->assertSee('Direct sponsor income (L1)');
        $response->assertSee('₹160.00');
        $response->assertSee('Matching cascade income');
        $response->assertSee('₹80.00');
        $response->assertSee('Level 1');
        $response->assertSee('Child Level 1');
    }

    public function test_plans_index_shows_level_distribution_link_and_cascade_amounts(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.plans.index'));

        $response->assertStatus(200);
        $response->assertSee('Level distribution');
        $response->assertSee('Cascade (7 levels');
    }

    public function test_settings_page_shows_level_wise_distribution_model(): void
    {
        $response = $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'));

        $response->assertStatus(200);
        $response->assertSee('3. Level-wise matching income distribution (% per level)');
        $response->assertSee('Level-wise income report');
        $response->assertSee('Live Compensation Calculation & Solvency Check', false);
    }

    public function test_admin_can_configure_custom_level_wise_percentages_and_change_level_count(): void
    {
        // Decrease to 5 levels with custom tiered percentages: 15%, 12%, 10%, 8%, 5% (Sum = 50%)
        $response = $this->actingAs($this->admin, 'web')
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'gst_pct' => 12,
                'split_cost_pct' => 40,
                'split_pool_pct' => 40,
                'split_company_pct' => 20,
                'self_pct' => 10,
                'sponsor_pct' => 20,
                'rank_pool_pct' => 3,
                'match_cap_per_cycle' => 600000,
                'match_cap_per_week' => 150000,
                'circuit_breaker_pct' => 60,
                'levels' => [15, 12, 10, 8, 5],
            ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $response->assertSessionHas('status');
        $response->assertSessionMissing('calculation_warning');

        $this->assertSame([
            1 => 15.0,
            2 => 12.0,
            3 => 10.0,
            4 => 8.0,
            5 => 5.0,
        ], Setting::getLevelPercentages());
        $this->assertSame(5, (int) Setting::get('cascade_depth'));

        // Verify level report reflects the 5 custom levels
        $reportResponse = $this->actingAs($this->admin, 'web')->get(route('admin.reports.levels'));
        $reportResponse->assertStatus(200);
        $reportResponse->assertSee('Cascade: 50% (5 Levels)');
        $reportResponse->assertSee('15%');
        $reportResponse->assertSee('12%');
        $reportResponse->assertSee('5%');
    }

    public function test_settings_update_rejects_invalid_revenue_split_not_totalling_100(): void
    {
        $response = $this->actingAs($this->admin, 'web')
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'gst_pct' => 12,
                'split_cost_pct' => 50,
                'split_pool_pct' => 40,
                'split_company_pct' => 20, // Total = 110%
                'self_pct' => 10,
                'sponsor_pct' => 20,
                'rank_pool_pct' => 3,
                'match_cap_per_cycle' => 600000,
                'match_cap_per_week' => 150000,
                'circuit_breaker_pct' => 60,
                'levels' => [10, 10, 10],
            ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $response->assertSessionHasErrors('split_pool_pct');
    }

    public function test_settings_update_warns_and_guides_admin_when_total_bv_payout_exceeds_100_percent(): void
    {
        // Self 15% + Sponsor 25% + Levels (20*4 = 80%) + Rank 5% = 125% of BV (> 100%)
        $response = $this->actingAs($this->admin, 'web')
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'gst_pct' => 12,
                'split_cost_pct' => 40,
                'split_pool_pct' => 40,
                'split_company_pct' => 20,
                'self_pct' => 15,
                'sponsor_pct' => 25,
                'rank_pool_pct' => 5,
                'match_cap_per_cycle' => 600000,
                'match_cap_per_week' => 150000,
                'circuit_breaker_pct' => 60,
                'levels' => [20, 20, 20, 20],
            ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $response->assertSessionHas('calculation_warning');
        $warning = session('calculation_warning');
        $this->assertStringContainsString('125%', $warning);
        $this->assertStringContainsString('exceeds 100% of BV by 25%', $warning);
        $this->assertStringContainsString('55%', $warning); // Recommended max sum for levels (100 - 15 - 25 - 5 = 55%)
    }

    public function test_compensation_engine_pays_exact_configured_level_wise_percentages(): void
    {
        // Configure 3 levels with distinct percentages: L1 = 15%, L2 = 10%, L3 = 5%
        Setting::setLevelPercentages([15, 10, 5]);

        // Create chain: L3Ancestor -> L2Ancestor -> L1Node -> LeftChild & RightChild
        $l3 = Member::create([
            'member_code' => 'BSLVL300',
            'name' => 'Level 3 Ancestor',
            'phone' => '9000000003',
            'status' => 'active',
        ]);
        $l3->update(['path' => $l3->id.'.']);

        $l2 = Member::create([
            'member_code' => 'BSLVL200',
            'name' => 'Level 2 Ancestor',
            'phone' => '9000000002',
            'sponsor_id' => $l3->id,
            'placement_id' => $l3->id,
            'position' => 'left',
            'path' => $l3->path,
            'status' => 'active',
        ]);
        $l2->update(['path' => $l3->path.$l2->id.'.']);

        $l1 = Member::create([
            'member_code' => 'BSLVL100',
            'name' => 'Level 1 Node (Matching Root)',
            'phone' => '9000000001',
            'sponsor_id' => $l2->id,
            'placement_id' => $l2->id,
            'position' => 'left',
            'path' => $l2->path,
            'status' => 'active',
        ]);
        $l1->update(['path' => $l2->path.$l1->id.'.']);

        $leftChild = Member::create([
            'member_code' => 'BSLEFT01',
            'name' => 'Left Child',
            'phone' => '9000000011',
            'sponsor_id' => $l1->id,
            'placement_id' => $l1->id,
            'position' => 'left',
            'path' => $l1->path,
            'status' => 'active',
        ]);
        $leftChild->update(['path' => $l1->path.$leftChild->id.'.']);

        $rightChild = Member::create([
            'member_code' => 'BSRGHT01',
            'name' => 'Right Child',
            'phone' => '9000000012',
            'sponsor_id' => $l1->id,
            'placement_id' => $l1->id,
            'position' => 'right',
            'path' => $l1->path,
            'status' => 'active',
        ]);
        $rightChild->update(['path' => $l1->path.$rightChild->id.'.']);

        // Create paid orders on both LeftChild and RightChild so L1 has 800 BV on left and 800 BV on right
        Order::create([
            'order_code' => 'ORD-L1',
            'member_id' => $leftChild->id,
            'plan_id' => $this->plan->id,
            'amount' => 2000,
            'bv' => 800,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Order::create([
            'order_code' => 'ORD-R1',
            'member_id' => $rightChild->id,
            'plan_id' => $this->plan->id,
            'amount' => 2000,
            'bv' => 800,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $cycle = Cycle::create([
            'period_start' => now()->subDay(),
            'period_end' => now()->addDay(),
            'status' => 'draft',
        ]);

        $engine = new CompensationEngine;
        $engine->run($cycle);

        // L1 matched 800 BV -> L1 (15% of 800 = 120), L2 (10% of 800 = 80), L3 (5% of 800 = 40)
        $l1Match = LedgerEntry::where('cycle_id', $cycle->id)
            ->where('type', 'matching')
            ->where('member_id', $l1->id)
            ->where('source_member_id', $l1->id)
            ->first();
        $this->assertNotNull($l1Match);
        $this->assertEquals(120.00, (float) $l1Match->amount);
        $this->assertStringContainsString('Level 1', $l1Match->description);

        $l2Match = LedgerEntry::where('cycle_id', $cycle->id)
            ->where('type', 'matching')
            ->where('member_id', $l2->id)
            ->where('source_member_id', $l1->id)
            ->first();
        $this->assertNotNull($l2Match);
        $this->assertEquals(80.00, (float) $l2Match->amount);
        $this->assertStringContainsString('Level 2', $l2Match->description);

        $l3Match = LedgerEntry::where('cycle_id', $cycle->id)
            ->where('type', 'matching')
            ->where('member_id', $l3->id)
            ->where('source_member_id', $l1->id)
            ->first();
        $this->assertNotNull($l3Match);
        $this->assertEquals(40.00, (float) $l3Match->amount);
        $this->assertStringContainsString('Level 3', $l3Match->description);
    }

    public function test_admin_member_module_table_profile_and_visual_binary_tree(): void
    {
        $root = Member::create([
            'member_code' => 'BSROOT99',
            'name' => 'Root Leader',
            'phone' => '9888888881',
            'rank' => 'gold',
            'status' => 'active',
        ]);
        $root->update(['path' => $root->id.'.']);

        $leftChild = Member::create([
            'member_code' => 'BSLEFT99',
            'name' => 'Left Partner',
            'phone' => '9888888882',
            'sponsor_id' => $root->id,
            'placement_id' => $root->id,
            'position' => 'left',
            'path' => $root->path,
            'rank' => 'silver',
            'status' => 'active',
        ]);
        $leftChild->update(['path' => $root->path.$leftChild->id.'.']);

        // 1. Test Admin Members Index Table & Filters
        $indexResponse = $this->actingAs($this->admin, 'web')->get(route('admin.members.index', ['rank' => 'gold']));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Registered Members & Network Overview');
        $indexResponse->assertSee('Binary Leg Volume (BV)');
        $indexResponse->assertSee('Root Leader');
        $indexResponse->assertDontSee('Left Partner');

        // 2. Test Admin Member 360 Profile Report
        $showResponse = $this->actingAs($this->admin, 'web')->get(route('admin.members.show', $root));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Income Breakdown by Stream');
        $showResponse->assertSee('Account, Upline & Payout Details');
        $showResponse->assertSee('Recent Ledger & Payout Statement');

        // 3. Test Admin Member Network Visual Binary Genealogy Tree & Hierarchy Table
        $networkResponse = $this->actingAs($this->admin, 'web')->get(route('admin.members.network', $root));
        $networkResponse->assertStatus(200);
        $networkResponse->assertSee('Binary Placement Genealogy Tree (Team Tree)');
        $networkResponse->assertSee('genealogy-tree', false);
        $networkResponse->assertSee('LEFT · L2');
        $networkResponse->assertSee('Vacant Right');
        $networkResponse->assertSee('Left Partner');
    }

    public function test_member_portal_downline_tree_team_and_levels_tables(): void
    {
        $member = Member::create([
            'member_code' => 'BSMEM100',
            'name' => 'Portal Member',
            'phone' => '9777777771',
            'status' => 'active',
        ]);
        $member->update(['path' => $member->id.'.']);

        $direct = Member::create([
            'member_code' => 'BSDIR101',
            'name' => 'Direct Member',
            'phone' => '9777777772',
            'sponsor_id' => $member->id,
            'placement_id' => $member->id,
            'position' => 'right',
            'path' => $member->path,
            'status' => 'active',
        ]);
        $direct->update(['path' => $member->path.$direct->id.'.']);

        $downlineResp = $this->actingAs($member, 'member')->get(route('member.downline.index'));
        $downlineResp->assertStatus(200);
        $downlineResp->assertSee('Team Binary Genealogy Tree');
        $downlineResp->assertSee('Vacant Left');
        $downlineResp->assertSee('RIGHT · L2');
        $downlineResp->assertSee('Direct Member');

        $teamResp = $this->actingAs($member, 'member')->get(route('member.team.index'));
        $teamResp->assertStatus(200);
        $teamResp->assertSee('Direct Referrals Directory');
        $teamResp->assertSee('Direct Member');

        $levelsResp = $this->actingAs($member, 'member')->get(route('member.levels.index'));
        $levelsResp->assertStatus(200);
        $levelsResp->assertSee('Generation-by-Generation Level Breakdown');

        $reportsResp = $this->actingAs($member, 'member')->get(route('member.reports.index'));
        $reportsResp->assertStatus(200);
        $reportsResp->assertSee('My team, at a glance');
    }
}
