<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole business, run through the real screens with three regions: create regions and plans as an
 * admin, register members by link, buy, pay, verify, approve a cycle, then check that nothing ever
 * crosses a region and that every report still works.
 */
class RegionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        foreach (['self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 1, 'circuit_breaker_pct' => 50, 'match_cap_per_cycle' => 10000000, 'match_cap_per_week' => 10000000] as $k => $v) {
            Setting::set($k, $v);
        }
        Setting::setEligibilityMinVolumes([0], 0);

        $cat = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $this->product = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
    }

    private function region(string $name, string $code): Region
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.regions.store'), ['name' => $name, 'code' => $code])->assertRedirect();

        return Region::where('code', strtoupper($code))->firstOrFail();
    }

    private function plan(Region $region, string $name, float $price, float $bv): Plan
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), [
            'region_id' => $region->id, 'name' => $name, 'price' => $price, 'bv' => $bv, 'is_active' => 1,
            'qty' => [$this->product->id => 1],
        ])->assertRedirect(route('admin.plans.index'));

        return Plan::where('name', $name)->firstOrFail();
    }

    private function join(array $fields): Member
    {
        $this->app['auth']->guard('member')->logout();
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        $this->post(route('register.send'), $fields + ['name' => 'Person '.$fields['phone']]);
        $this->assertEmpty(session('errors')?->all() ?? [], 'Registration refused: '.json_encode(session('errors')?->all()));
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        return Member::where('phone', $fields['phone'])->firstOrFail();
    }

    private function buyAndPay(Member $m, Plan $plan): Order
    {
        $this->app['auth']->guard('web')->logout();
        $this->actingAs($m, 'member')->post(route('member.plans.buy', $plan));
        $order = Order::where('member_id', $m->id)->latest('id')->firstOrFail();

        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $order), [
            'method' => 'upi', 'reference' => 'UTR'.$order->id, 'billing_name' => $m->name, 'billing_phone' => $m->phone,
        ]);
        $payment = Payment::where('order_id', $order->id)->firstOrFail();

        $this->app['auth']->guard('member')->logout();
        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment));

        return $order->fresh();
    }

    public function test_three_regions_end_to_end(): void
    {
        // 1. Admin sets up regions and each region's own plans.
        $gj = $this->region('Gujarat', 'GJ');
        $mh = $this->region('Maharashtra', 'MH');
        $dl = $this->region('Delhi', 'DL');
        $gjPlan = $this->plan($gj, 'GJ Essential', 10000, 4000);
        $mhPlan = $this->plan($mh, 'MH Essential', 5000, 2000);
        $dlPlan = $this->plan($dl, 'DL Essential', 2000, 800);

        // 2. Members join by region link and by sponsor links (left / right).
        $g1 = $this->join(['phone' => '9100000001', 'region' => 'GJ']);
        $g2 = $this->join(['phone' => '9100000002', 'sponsor_code' => $g1->member_code, 'side' => 'left']);
        $g3 = $this->join(['phone' => '9100000003', 'sponsor_code' => $g1->member_code, 'side' => 'right']);
        $m1 = $this->join(['phone' => '9200000001', 'region' => 'MH']);
        $m2 = $this->join(['phone' => '9200000002', 'sponsor_code' => $m1->member_code, 'side' => 'left']);
        $m3 = $this->join(['phone' => '9200000003', 'sponsor_code' => $m1->member_code, 'side' => 'right']);
        $d1 = $this->join(['phone' => '9300000001', 'region' => 'DL']);

        // 3. Trees are separate: three roots, correct legs, all inside their own region.
        $this->assertSame(3, Member::whereNull('placement_id')->count());
        $this->assertSame([$g1->id, 'left'], [$g2->fresh()->placement_id, $g2->fresh()->position]);
        $this->assertSame([$g1->id, 'right'], [$g3->fresh()->placement_id, $g3->fresh()->position]);
        foreach ([[$g2, $gj], [$g3, $gj], [$m2, $mh], [$m3, $mh], [$d1, $dl]] as [$mem, $reg]) {
            $this->assertSame($reg->id, $mem->fresh()->region_id);
        }

        // 4. A mobile number cannot hop regions, and nobody can sponsor across regions.
        $this->app['auth']->guard('member')->logout();
        $this->flushSession();
        $this->post(route('register.send'), ['name' => 'Hop', 'phone' => '9100000001', 'region' => 'MH'])->assertSessionHasErrors('phone');
        $this->assertStringContainsString('Gujarat', session('errors')->first('phone'));
        $this->post(route('register.send'), ['name' => 'Cross', 'phone' => '9400000001', 'sponsor_code' => $g1->member_code, 'region' => 'DL'])->assertSessionHasErrors('sponsor_code');

        // 5. Everyone buys their own region's plan; admin verifies the payments.
        foreach ([[$g1, $gjPlan], [$g2, $gjPlan], [$g3, $gjPlan], [$m1, $mhPlan], [$m2, $mhPlan], [$m3, $mhPlan], [$d1, $dlPlan]] as [$mem, $plan]) {
            $order = $this->buyAndPay($mem, $plan);
            $this->assertSame('paid', $order->status, "{$mem->member_code} order should be paid");
            $this->assertSame($mem->fresh()->region_id, $order->plan->region_id);
        }

        // ...and a cross-region purchase is refused.
        $this->actingAs($g2, 'member')->post(route('member.plans.buy', $mhPlan))->assertSessionHasErrors('plan');
        $this->assertSame(0, Order::where('member_id', $g2->id)->where('plan_id', $mhPlan->id)->count());

        // 6. One cycle settles all regions at once.
        $this->actingAs($this->admin, 'web')->post(route('admin.cycles.store'), ['period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()]);
        $cycle = Cycle::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', $cycle))->assertSessionHasNoErrors();
        $this->assertSame('approved', $cycle->fresh()->status);
        $this->assertSame(0, Order::where('status', 'paid')->count());

        // 7. Money never crosses a region: every ledger line's earner and source are in the same region.
        $cross = LedgerEntry::whereNotNull('source_member_id')->get()
            ->filter(fn ($e) => Member::find($e->member_id)->region_id !== Member::find($e->source_member_id)->region_id);
        $this->assertCount(0, $cross, 'Income was paid across regions');

        // 8. Per-region sponsor income is exactly 20% of that region's BV (direct referrals only here).
        $sponsorOf = fn (Region $r) => (float) LedgerEntry::where('type', 'sponsor')->whereIn('member_id', Member::where('region_id', $r->id)->pluck('id'))->sum('amount');
        $this->assertEquals(2 * 0.20 * 4000, $sponsorOf($gj));
        $this->assertEquals(2 * 0.20 * 2000, $sponsorOf($mh));
        $this->assertEquals(0.0, $sponsorOf($dl));

        // 9. Matching is paid inside each tree only: roots earn on their own two legs.
        $matchOf = fn (Member $m) => (float) LedgerEntry::where('type', 'matching')->where('member_id', $m->id)->sum('amount');
        $this->assertGreaterThan(0, $matchOf($g1));
        $this->assertGreaterThan(0, $matchOf($m1));
        $this->assertEquals(0.0, $matchOf($d1));

        // 9b. Reports: each region's figures add up exactly to the all-regions figures.
        $from = now()->startOfMonth()->subDay();
        $to = now()->endOfMonth()->addDay();
        $all = (new \App\Services\ReportService)->businessOverview($from, $to);
        $parts = collect([$gj, $mh, $dl])->map(fn ($r) => (new \App\Services\ReportService)->forRegion($r->id)->businessOverview($from, $to));
        $this->assertSame(7, $all['order_count']);
        $this->assertSame([3, 3, 1], $parts->pluck('order_count')->all());
        $this->assertEquals(round($all['revenue'], 2), round($parts->sum('revenue'), 2));
        $this->assertEquals(round($all['bv'], 2), round($parts->sum('bv'), 2));
        $this->assertEquals(round($all['paid_out'], 2), round($parts->sum('paid_out'), 2));
        $this->assertEquals(3 * 4000.0, $parts[0]['bv']);

        // The same holds on screen: a region's report shows only that region's plans and people.
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.sales', ['region' => $mh->id]))
            ->assertOk()->assertSee('MH Essential')->assertDontSee('GJ Essential')->assertDontSee('DL Essential');
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.sales'))
            ->assertOk()->assertSee('MH Essential')->assertSee('GJ Essential');
        $this->actingAs($this->admin, 'web')->get(route('admin.reports.overview'))
            ->assertOk()->assertSee('Compare regions')->assertSee('Gujarat')->assertSee('Delhi');

        // 9c. A closed region takes no new orders, but its members keep their accounts.
        $dl->update(['is_active' => false]);
        $this->actingAs($d1, 'member')->post(route('member.plans.buy', $dlPlan))->assertSessionHasErrors('plan');

        // 10. Every report page and CSV opens, with and without a region filter.
        $this->app['auth']->guard('member')->logout();
        foreach (['overview', 'sales', 'repurchase', 'payouts', 'levels', 'pending', 'compliance'] as $r) {
            $this->actingAs($this->admin, 'web')->get(route("admin.reports.$r"))->assertOk();
            $this->actingAs($this->admin, 'web')->get(route("admin.reports.$r", ['region' => $gj->id]))->assertOk();
        }
        foreach (['admin.regions.index', 'admin.members.index', 'admin.plans.index', 'admin.orders.index', 'admin.payments.index', 'admin.cycles.index', 'admin.guide.index', 'admin.dashboard'] as $route) {
            $this->actingAs($this->admin, 'web')->get(route($route))->assertOk();
        }
    }
}
