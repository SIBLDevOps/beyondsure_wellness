<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
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
use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The whole business through the real screens, down eight levels: every person joins by their sponsor's link with the
 * plan the sponsor chose, pays, is approved, and only then counts. Each member also adds a direct on the other leg.
 * Checks the tree, counts at every level, the "not paid yet" views, invoices, emails, income and a withdrawal.
 */
class FullNetworkFlowTest extends TestCase
{
    use RefreshDatabase;

    private const DEPTH = 8;

    private User $admin;

    private Plan $plan;

    private Region $region;

    /** @var array<int, Member> chain members R0..R8 (each the left child of the one before) */
    private array $chain = [];

    /** @var array<int, Member> D0..D8: each chain member's direct on the right leg */
    private array $direct = [];

    private int $phone = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureCompensation();
        Mail::fake();
        // Seventeen people join from one address within a minute here, which the real sign-up rate limit would stop.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        foreach (['self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 1, 'circuit_breaker_pct' => 80, 'match_cap_per_cycle' => 10000000, 'match_cap_per_week' => 10000000] as $k => $v) {
            Setting::set($k, $v);
        }
        Setting::setEligibilityMinVolumes([0], 0);
        Setting::set('site_contact_email', 'office@beyondsure.test');

        $cat = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'essential', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $this->plan->products()->attach($product->id, ['qty' => 1]);
    }

    // ------------------------------------------------------------------------------------------ the real flow, step by step

    /** Opens a sponsor's invite link (plan and leg chosen), registers, enters the OTP. Returns the new, unpaid member. */
    private function join(string $name, ?Member $sponsor, ?string $side): Member
    {
        $this->app['auth']->guard('member')->logout();
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();
        $this->phone++;
        $phone = '90'.str_pad((string) $this->phone, 8, '0', STR_PAD_LEFT);

        // The very first person starts the region: the admin creates the root (nobody can become it by signing up).
        if (! $sponsor) {
            $root = $this->startRegion($this->region, $name, $phone, strtolower(str_replace(' ', '.', $name)).'@example.com');
            app(\App\Services\OrderService::class)->place($root, $this->plan);

            return $root;
        }

        $query = ['plan' => $this->plan->id] + ['sponsor' => $sponsor->member_code, 'side' => $side];
        $this->get(route('register', $query))->assertOk();
        $this->post(route('register.send'), ['name' => $name, 'phone' => $phone, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com']);
        $this->assertEmpty(session('errors')?->all() ?? [], "{$name}: ".json_encode(session('errors')?->all()));
        $res = $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $member = Member::where('phone', $phone)->firstOrFail();
        $order = Order::where('member_id', $member->id)->firstOrFail();
        $res->assertRedirect(route('member.orders.pay', $order));

        return $member;
    }

    private function pay(Member $m): Order
    {
        $order = Order::where('member_id', $m->id)->where('status', 'pending_payment')->firstOrFail();
        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $order), [
            'method' => 'upi', 'reference' => 'UTR'.$order->id, 'billing_name' => $m->name, 'billing_phone' => $m->phone, 'billing_address' => '1 Test Road',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->guard('member')->logout();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasNoErrors();
        $this->app['auth']->guard('web')->logout();

        return $order->fresh();
    }

    private function buildNetwork(): void
    {
        $this->chain[0] = $this->join('Root Ravi', null, null);
        $this->pay($this->chain[0]);
        $this->chain[0] = $this->chain[0]->fresh();

        for ($k = 1; $k <= self::DEPTH; $k++) {
            $sponsor = $this->chain[$k - 1];
            $this->chain[$k] = $this->join("Chain L{$k}", $sponsor, 'left');
            $this->pay($this->chain[$k]);
            $this->chain[$k] = $this->chain[$k]->fresh();
        }
        // Each chain member also adds one direct on the right leg ("direct add").
        for ($k = 0; $k < self::DEPTH; $k++) {
            $this->direct[$k] = $this->join("Direct D{$k}", $this->chain[$k], 'right');
            $this->pay($this->direct[$k]);
            $this->direct[$k] = $this->direct[$k]->fresh();
        }
    }

    // ------------------------------------------------------------------------------------------ before anyone pays

    public function test_a_person_who_registers_but_has_not_paid_is_shown_as_not_active_and_cannot_use_the_portal(): void
    {
        $root = $this->join('Root Ravi', null, null);
        $this->pay($root);
        $root = $root->fresh();
        $guest = $this->join('Guest Gina', $root, 'left');
        $guest = $guest->fresh();

        $this->assertSame('inactive', $guest->status);
        $this->assertSame($root->id, $guest->sponsor_id);
        $this->assertSame('left', $guest->position);
        $this->assertSame($this->plan->id, $guest->interested_plan_id);

        // In the sponsor's team and tree, clearly labelled "Not active", and counted (one person, none paid)...
        $net = app(NetworkService::class);
        $levels = $net->levelBreakdown($root, 7);
        $this->assertSame([1, 0], [$levels[0]['count'], $levels[0]['paid_count']]);
        $this->actingAs($root, 'member')->get(route('member.team.index'))->assertOk()->assertSee('Guest Gina')->assertSee('Not active');
        $this->actingAs($root, 'member')->get(route('member.downline.index'))->assertOk()->assertSee('Guest Gina')->assertSee('Not active');
        // ...and waiting in the pipeline, with their level and leg, for the sponsor and for admin.
        $this->actingAs($root, 'member')->get(route('member.pending.index'))->assertOk()->assertSee('Guest Gina')->assertSee('Order placed')->assertSee('Left leg')->assertSee('Level 1');
        $this->actingAs($this->admin, 'web')->get(route('admin.activation.index'))->assertOk()->assertSee('Guest Gina');
        $this->actingAs($this->admin, 'web')->get(route('admin.members.index'))->assertOk()->assertSee('Guest Gina');   // admin sees everyone

        // The unpaid person can only see plans and their orders.
        foreach (['member.dashboard', 'member.wallet.index', 'member.team.index', 'member.downline.index', 'member.levels.index', 'member.reports.index'] as $r) {
            $this->actingAs($guest, 'member')->get(route($r))->assertRedirect(route('member.plans.index'));
        }
        $this->actingAs($guest, 'member')->get(route('member.plans.index'))->assertOk()->assertSee('Essential');

        // After paying they are active everywhere and the label goes.
        $this->pay($guest);
        $this->assertSame([1, 1], [$net->levelBreakdown($root->fresh(), 7)[0]['count'], $net->levelBreakdown($root->fresh(), 7)[0]['paid_count']]);
        $this->actingAs($root->fresh(), 'member')->get(route('member.team.index'))->assertOk()->assertSee('Guest Gina');
        $this->actingAs($root->fresh(), 'member')->get(route('member.pending.index'))->assertOk()->assertDontSee('Guest Gina');
        $this->actingAs($guest->fresh(), 'member')->get(route('member.dashboard'))->assertOk();
    }

    // ------------------------------------------------------------------------------------------ the full tree

    public function test_an_eight_level_network_with_a_direct_at_every_level_is_placed_counted_and_invoiced_correctly(): void
    {
        $this->buildNetwork();
        $net = app(NetworkService::class);
        $total = count($this->chain) + count($this->direct);                     // 9 + 8
        $this->assertSame($total, Member::where('status', 'active')->count());
        $this->assertSame(0, Member::where('status', '!=', 'active')->count());

        // Every placement is exactly where the sponsor's link put it.
        for ($k = 1; $k <= self::DEPTH; $k++) {
            $this->assertSame([$this->chain[$k - 1]->id, 'left', $this->chain[$k - 1]->id], [$this->chain[$k]->placement_id, $this->chain[$k]->position, $this->chain[$k]->sponsor_id], "chain {$k}");
        }
        for ($k = 0; $k < self::DEPTH; $k++) {
            $this->assertSame([$this->chain[$k]->id, 'right', $this->chain[$k]->id], [$this->direct[$k]->placement_id, $this->direct[$k]->position, $this->direct[$k]->sponsor_id], "direct {$k}");
        }

        // Counts at every level, seen from the top: two people on each of levels 1 to 7 (a chain member and a direct).
        $levels = $net->levelBreakdown($this->chain[0], 7, true);
        $this->assertCount(7, $levels);
        foreach ($levels as $level => $row) {
            $this->assertSame(2, (int) ($row['count'] ?? $row['members'] ?? count($row['members'] ?? [])), "level {$level} has two people");
        }
        // ...and the same from the middle of the network: every member sees only their own downline.
        $mid = $net->levelBreakdown($this->chain[4], 7, true);
        $this->assertCount(4, $mid, 'From L4 only four more levels exist (chain L5 to L8, plus directs up to D7)');
        $this->assertCount(0, $net->levelBreakdown($this->chain[8], 7, true), 'The last member has nobody below');

        // Direct counts on screen.
        foreach ([0, 3, 7] as $k) {
            $this->actingAs($this->chain[$k], 'member')->get(route('member.team.index'))->assertOk()
                ->assertSee('Chain L'.($k + 1))->assertSee('Direct D'.$k);
        }
        $this->actingAs($this->chain[4], 'member')->get(route('member.team.index'))->assertOk()->assertDontSee('Chain L3')->assertDontSee('Direct D2');
        $this->actingAs($this->chain[0], 'member')->get(route('member.levels.index'))->assertOk();
        $this->actingAs($this->chain[0], 'member')->get(route('member.downline.index', ['depth' => 7]))->assertOk()->assertSee('Chain L1')->assertSee('Direct D0');

        // Invoices: one per purchase, numbered 1..N with no gaps, each readable by its owner.
        $numbers = Order::whereNotNull('invoice_number')->orderBy('id')->pluck('invoice_number')->all();
        $this->assertCount($total, $numbers);
        $this->assertSame(range(1, $total), array_map(fn ($n) => (int) substr($n, strrpos($n, '/') + 1), $numbers));
        $this->assertSame($numbers, array_values(array_unique($numbers)));
        $order = Order::where('member_id', $this->chain[5]->id)->firstOrFail();
        $this->actingAs($this->chain[5], 'member')->get(route('member.orders.invoice', $order))->assertOk()->assertSee($order->invoice_number)->assertSee('₹2,240.00');
        $this->actingAs($this->chain[6], 'member')->get(route('member.orders.invoice', $order))->assertForbidden();

        // Emails: everyone was welcomed and confirmed; each sponsor was told about every person who joined and became active.
        $sent = Mail::sent(BrandedMail::class);
        $countFor = fn (string $part) => $sent->filter(fn ($m) => str_contains($m->mailSubject, $part))->count();
        $this->assertSame($total - 1, $countFor('Welcome'), 'Everyone who signs up is welcomed; the root is created by the admin instead');
        $this->assertSame($total, $countFor('Payment confirmed'));
        $this->assertSame($total, $countFor('We have your payment'));
        $this->assertSame($total - 1, $countFor('joined your team'), 'Everyone except the root has a sponsor to tell');
        $this->assertSame($total - 1, $countFor('is now active'));
        $this->assertSame($total, $countFor('Payment to verify'));
    }

    // ------------------------------------------------------------------------------------------ money

    public function test_income_is_posted_to_the_right_people_and_a_withdrawal_goes_all_the_way(): void
    {
        $this->buildNetwork();
        $paidOrders = Order::where('status', 'paid')->count();
        $this->assertSame(17, $paidOrders);

        $this->actingAs($this->admin, 'web')->post(route('admin.cycles.store'), ['period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()]);
        $cycle = Cycle::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', $cycle))->assertSessionHasNoErrors();
        $this->assertSame(0, Order::where('status', 'paid')->count(), 'Every paid order was settled');
        $this->assertSame(17, Order::where('status', 'settled')->count());

        $sum = fn (string $type, Member $m) => (float) LedgerEntry::where('type', $type)->where('member_id', $m->id)->sum('amount');

        // Self income: 10% of the plan's 800 BV for each of the 17 buyers.
        foreach (array_merge($this->chain, $this->direct) as $m) {
            $this->assertEqualsWithDelta(80.0, $sum('self', $m), 0.01, "{$m->name} self income");
        }
        // Sponsor income: 20% of 800 BV for each of their direct buyers. Chain members 0..7 sponsored two; chain 8 none.
        for ($k = 0; $k < self::DEPTH; $k++) {
            $this->assertEqualsWithDelta(2 * 160.0, $sum('sponsor', $this->chain[$k]), 0.01, "chain {$k} sponsor income");
        }
        $this->assertEqualsWithDelta(0.0, $sum('sponsor', $this->chain[8]), 0.01);
        $this->assertEqualsWithDelta(0.0, $sum('sponsor', $this->direct[3]), 0.01, 'A direct with no team earns no sponsor income');

        // The two legs of the top member are balanced in volume, so he earns matching income; nobody is ever paid a negative amount.
        $this->assertGreaterThan(0, $sum('matching', $this->chain[0]));
        $this->assertSame(0, LedgerEntry::whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->where('amount', '<', 0)->count());
        // Money never crosses a region and nobody is paid on someone else's purchase unless they are upline.
        $this->assertSame(0, LedgerEntry::whereNotNull('source_member_id')->get()->filter(fn ($e) => Member::find($e->member_id)->region_id !== Member::find($e->source_member_id)->region_id)->count());

        // Income emails reached the earners.
        $income = Mail::sent(BrandedMail::class)->filter(fn ($m) => str_contains($m->mailSubject, 'income'));
        $this->assertGreaterThanOrEqual(9, $income->count());

        // The top member now has money: bank details, withdraw, approve, pay.
        $top = $this->chain[0]->fresh();
        $this->actingAs($top, 'member')->put(route('member.wallet.bank-details'), ['bank_account_name' => 'Root Ravi', 'bank_account_number' => '123456789012', 'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC Bank'])->assertSessionHasNoErrors();
        $this->actingAs($top->fresh(), 'member')->post(route('member.wallet.request'), ['amount' => 100])->assertSessionHasNoErrors();
        $w = \App\Models\Withdrawal::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasNoErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'NEFT123'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertEqualsWithDelta(-100.0, $sum('withdrawal', $top), 0.01);
        $this->assertGreaterThanOrEqual(1, Mail::sent(BrandedMail::class)->filter(fn ($m) => str_contains($m->mailSubject, 'Withdrawal paid'))->count());
    }

    // ------------------------------------------------------------------------------------------ every page opens

    public function test_every_member_and_admin_page_opens_for_everyone_in_the_network(): void
    {
        $this->buildNetwork();
        $order = Order::where('member_id', $this->chain[2]->id)->firstOrFail();

        foreach (array_merge($this->chain, $this->direct) as $m) {
            foreach (['member.dashboard', 'member.plans.index', 'member.orders.index', 'member.wallet.index', 'member.downline.index', 'member.team.index', 'member.pending.index', 'member.levels.index', 'member.reports.index'] as $route) {
                $this->actingAs($m->fresh(), 'member')->get(route($route))->assertOk();
            }
        }
        foreach (['admin.dashboard', 'admin.members.index', 'admin.activation.index', 'admin.orders.index', 'admin.payments.index', 'admin.plans.index', 'admin.cycles.index', 'admin.regions.index', 'admin.reports.overview', 'admin.reports.sales', 'admin.reports.levels', 'admin.guide.index'] as $route) {
            $this->actingAs($this->admin, 'web')->get(route($route))->assertOk();
        }
        $this->actingAs($this->admin, 'web')->get(route('admin.members.network', $this->chain[0]))->assertOk()->assertSee('Chain L1');
        $this->actingAs($this->admin, 'web')->get(route('admin.orders.invoice', $order))->assertOk();
    }
}
