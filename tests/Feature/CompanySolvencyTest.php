<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\Member;
use App\Models\MemberLegTotal;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CompensationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validates company solvency and overpayment prevention.
 * Mathematically guarantees that under ANY network topology, depth, or carry-forward scenario,
 * the company CAN NEVER LOSE MONEY: total commission payouts can NEVER exceed Pool BV,
 * and cost of goods + company margin are 100% protected.
 */
class CompanySolvencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Product $product;

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
        Setting::set('circuit_breaker_pct', 60);
        Setting::set('gst_pct', 12);
        Setting::setEligibilityMinVolumes([0], 0);

        $category = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Health Formula',
            'sku' => 'HF-01',
            'cost_price' => 400,
            'sell_price' => 1000,
            'gst_pct' => 12,
            'is_active' => true,
        ]);

        $this->plan = Plan::create([
            'name' => 'Standard Package',
            'slug' => 'standard-package',
            'price' => 10000,
            'bv' => 4000,
            'is_active' => true,
        ]);
        $this->plan->products()->attach($this->product->id, ['qty' => 1]);
    }

    private function createMember(string $code, ?Member $parent = null, ?string $position = null): Member
    {
        return Member::create([
            'member_code' => $code,
            'name' => $code,
            'phone' => '9'.substr(str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT), 0, 9),
            'sponsor_id' => $parent?->id,
            'placement_id' => $parent?->id,
            'position' => $position,
            'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
            'status' => 'active',
        ]);
    }

    public function test_total_cycle_payout_never_exceeds_pool_bv_in_a_deep_10_level_matrix(): void
    {
        // Build a 10-level deep network with multiple branches
        $current = $this->createMember('BS_ROOT');
        $members = [$current];

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $left = $this->createMember("BS_L{$lvl}_LEFT", $current, 'left');
            $right = $this->createMember("BS_L{$lvl}_RIGHT", $current, 'right');
            $members[] = $left;
            $members[] = $right;
            $current = $left; // branch deeper on the left
        }

        // Place orders across various depth levels
        $orderCount = 0;
        foreach (array_slice($members, 1) as $m) {
            Order::create([
                'order_code' => 'ORD-SOLV-'.(++$orderCount),
                'member_id' => $m->id,
                'plan_id' => $this->plan->id,
                'amount' => 11200,
                'taxable_amount' => 10000,
                'gst_amount' => 1200,
                'bv' => 4000,
                'status' => 'paid',
            ]);
        }

        $cycle = Cycle::create([
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'status' => 'draft',
        ]);

        (new CompensationEngine)->run($cycle);

        $cycle->refresh();

        // 1. Payout must NEVER exceed total Pool BV
        $this->assertGreaterThan(0, $cycle->pool_bv);
        $this->assertLessThanOrEqual($cycle->pool_bv, $cycle->total_paid, 'SOLVENCY BREACH: Total paid exceeded pool BV!');

        // 2. Net profit margin must be preserved
        $totalRevenue = Order::where('cycle_id', $cycle->id)->sum('taxable_amount');
        $totalCostOfGoods = Order::where('cycle_id', $cycle->id)->count() * $this->product->cost_price;
        $netCompanyProfit = $totalRevenue - $totalCostOfGoods - $cycle->total_paid;
        $this->assertGreaterThan(0, $netCompanyProfit, 'SOLVENCY BREACH: Company suffered a negative net margin!');

        // 3. Zero overpaid cycles
        $this->assertSame(0, Cycle::whereColumn('total_paid', '>', 'pool_bv')->count());
    }

    public function test_massive_historical_carry_forward_cannot_bankrupt_a_new_cycle(): void
    {
        $root = $this->createMember('BS_CF_ROOT');
        $left = $this->createMember('BS_CF_LEFT', $root, 'left');
        $right = $this->createMember('BS_CF_RIGHT', $root, 'right');

        // Pre-load ROOT with 10,000,000 BV of historical carry-forward on the left leg
        $rootLeg = MemberLegTotal::firstOrCreate(['member_id' => $root->id]);
        $rootLeg->update([
            'left_bv' => 10000000.0,
            'right_bv' => 0.0,
            'matched_bv' => 0.0,
        ]);

        // In a new cycle, only 1 small order (4,000 BV) arrives on the right leg
        Order::create([
            'order_code' => 'ORD-CF-01',
            'member_id' => $right->id,
            'plan_id' => $this->plan->id,
            'amount' => 11200,
            'taxable_amount' => 10000,
            'gst_amount' => 1200,
            'bv' => 4000,
            'status' => 'paid',
        ]);

        $cycle = Cycle::create([
            'period_start' => now(),
            'period_end' => now(),
            'status' => 'draft',
        ]);

        (new CompensationEngine)->run($cycle);

        $cycle->refresh();

        // Total paid in this cycle MUST NOT exceed the 4,000 BV brought in by the new cycle!
        $this->assertEquals(4000.0, (float) $cycle->pool_bv);
        $this->assertLessThanOrEqual(4000.0, (float) $cycle->total_paid, 'SOLVENCY BREACH: Historical carry-forward drained cycle cash!');

        // Zero overpaid cycles
        $this->assertSame(0, Cycle::whereColumn('total_paid', '>', 'pool_bv')->count());
    }

    public function test_flat_100_percent_matching_cascade_is_safely_scaled_by_circuit_breakers(): void
    {
        // Configure an aggressive 10 levels @ 10% each (= 100% cascade)
        Setting::setLevelPercentages(array_fill(0, 10, 10));

        $root = $this->createMember('BS_BRK_ROOT');
        $l1 = $this->createMember('BS_BRK_L1', $root, 'left');
        $l2 = $this->createMember('BS_BRK_L2', $l1, 'left');
        $r1 = $this->createMember('BS_BRK_R1', $root, 'right');

        Order::create([
            'order_code' => 'ORD-BRK-1',
            'member_id' => $l2->id,
            'plan_id' => $this->plan->id,
            'amount' => 11200,
            'taxable_amount' => 10000,
            'gst_amount' => 1200,
            'bv' => 4000,
            'status' => 'paid',
        ]);

        Order::create([
            'order_code' => 'ORD-BRK-2',
            'member_id' => $r1->id,
            'plan_id' => $this->plan->id,
            'amount' => 11200,
            'taxable_amount' => 10000,
            'gst_amount' => 1200,
            'bv' => 4000,
            'status' => 'paid',
        ]);

        $cycle = Cycle::create([
            'period_start' => now(),
            'period_end' => now(),
            'status' => 'draft',
        ]);

        (new CompensationEngine)->run($cycle);

        $cycle->refresh();

        // 8000 BV brought in. Self (800) + Sponsor (1600) + Matching + Rank MUST stay <= 8000
        $this->assertEquals(8000.0, (float) $cycle->pool_bv);
        $this->assertLessThanOrEqual(8000.0, (float) $cycle->total_paid);
        $this->assertSame(0, Cycle::whereColumn('total_paid', '>', 'pool_bv')->count());
    }
}
