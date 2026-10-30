<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Someone who has not bought anything yet sees only the plans to buy (and their own unpaid orders) — nothing else. */
class MemberPortalGateTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'description' => 'Daily syrup', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'description' => 'Start here', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($prod->id, ['qty' => 2]);
    }

    private function member(string $status = 'inactive'): Member
    {
        static $n = 0;
        $n++;

        return Member::create(['member_code' => 'G'.$n, 'name' => 'Gate '.$n, 'phone' => '97'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'status' => $status, 'region_id' => $this->region->id]);
    }

    private function order(Member $m, string $status = 'pending_payment'): Order
    {
        return Order::create(['order_code' => 'O'.random_int(100000, 999999), 'member_id' => $m->id, 'plan_id' => $this->plan->id, 'amount' => 2240, 'taxable_amount' => 2000, 'gst_amount' => 240, 'bv' => 800, 'status' => $status]);
    }

    private const HIDDEN_PAGES = [
        'member.dashboard', 'member.wallet.index', 'member.downline.index', 'member.team.index',
        'member.pending.index', 'member.levels.index', 'member.reports.index',
    ];

    public function test_a_member_who_has_not_bought_is_sent_to_the_plans_from_every_other_page(): void
    {
        $m = $this->member();

        foreach (self::HIDDEN_PAGES as $route) {
            $this->actingAs($m, 'member')->get(route($route))->assertRedirect(route('member.plans.index'));
        }
    }

    public function test_they_cannot_use_the_wallet_actions_by_posting_directly(): void
    {
        $m = $this->member();

        $this->actingAs($m, 'member')->post(route('member.wallet.request'), ['amount' => 500])->assertRedirect(route('member.plans.index'));
        $this->actingAs($m, 'member')->put(route('member.wallet.bank-details'), ['bank_account_name' => 'X'])->assertRedirect(route('member.plans.index'));
        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_they_can_still_see_the_plans_with_products_and_buy(): void
    {
        $m = $this->member();

        $this->actingAs($m, 'member')->get(route('member.plans.index'))->assertOk()
            ->assertSee('Essential')->assertSee('2× Syrup')->assertSee('₹2,240')->assertSee('Buy now');

        $this->actingAs($m, 'member')->post(route('member.plans.buy', $this->plan))->assertRedirect();
        $order = Order::where('member_id', $m->id)->firstOrFail();
        $this->actingAs($m, 'member')->get(route('member.orders.pay', $order))->assertOk()->assertSee('Order Summary');
    }

    public function test_the_menu_shows_only_buying_and_their_orders(): void
    {
        $m = $this->member();
        $this->order($m);

        $res = $this->actingAs($m, 'member')->get(route('member.plans.index'))->assertOk();

        $res->assertSee('Plans &amp; Bundles', false)->assertSee('My Orders');
        foreach (['Wallet &amp; Payouts', 'Earnings Report', 'Binary Tree', 'Direct Team', 'Pending customers', 'Level Income', 'Dashboard', 'Bank &amp; Wallet KYC'] as $hidden) {
            $res->assertDontSee($hidden, false);
        }
        // No invite code, wallet balance or rank until they have bought.
        $res->assertDontSee('Sponsor Code')->assertDontSee(route('member.wallet.index'), false)->assertDontSee('Rank');
    }

    public function test_the_plans_page_welcomes_a_new_customer_and_points_to_an_unpaid_order(): void
    {
        $m = $this->member();

        $this->actingAs($m, 'member')->get(route('member.plans.index'))->assertOk()
            ->assertSee('Choose a plan to get started')->assertDontSee('Complete your payment');

        $order = $this->order($m);
        $this->actingAs($m, 'member')->get(route('member.plans.index'))->assertOk()
            ->assertSee('Complete your payment')->assertSee(route('member.orders.pay', $order), false);
    }

    public function test_once_the_payment_is_approved_the_full_portal_opens(): void
    {
        $m = $this->member();
        $order = $this->order($m);
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'U1', 'status' => 'pending']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($m, 'member')->get(route('member.dashboard'))->assertRedirect(route('member.plans.index'));

        app(PaymentService::class)->verify($payment, $admin);

        foreach (self::HIDDEN_PAGES as $route) {
            $this->actingAs($m->fresh(), 'member')->get(route($route))->assertOk();
        }
        $this->actingAs($m->fresh(), 'member')->get(route('member.dashboard'))->assertSee('Wallet &amp; Payouts', false)->assertSee('Binary Tree');
    }

    public function test_an_active_member_is_never_held_back(): void
    {
        $m = $this->member('active');

        foreach (self::HIDDEN_PAGES as $route) {
            $this->actingAs($m, 'member')->get(route($route))->assertOk();
        }
    }

    public function test_signing_in_without_a_purchase_lands_on_the_plans_page(): void
    {
        $m = $this->member();
        $this->post(route('login.send'), ['phone' => $m->phone]);
        $res = $this->post(route('login.verify.submit'), ['otp' => session('debug_code')]);

        $res->assertRedirect(route('member.plans.index'));
    }

    public function test_the_gate_does_not_affect_signed_out_visitors_or_the_admin_area(): void
    {
        $this->get(route('member.dashboard'))->assertRedirect(route('login'));

        $admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin, 'web')->get(route('admin.dashboard'))->assertOk();
    }

    public function test_a_member_with_only_a_cancelled_order_is_still_treated_as_not_bought(): void
    {
        $m = $this->member();
        $this->order($m, 'cancelled');

        $this->actingAs($m, 'member')->get(route('member.wallet.index'))->assertRedirect(route('member.plans.index'));
    }
}
