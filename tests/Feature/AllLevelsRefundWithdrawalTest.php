<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Category;
use App\Models\Cycle;
use App\Models\CreditNote;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every level of the cascade, a refund (credit note) before and after income is posted, cancelling orders, and the
 * whole life of a withdrawal: request, reserve, approve, reject, pay. Built on an 8-level network with a direct on
 * every level, joined through the real screens.
 */
class AllLevelsRefundWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private const DEPTH = 8;

    private User $admin;

    private Plan $plan;

    /** @var array<int, Member> */
    private array $chain = [];

    /** @var array<int, Member> */
    private array $direct = [];

    private int $phone = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        foreach (['self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 1, 'circuit_breaker_pct' => 90, 'match_cap_per_cycle' => 100000000, 'match_cap_per_week' => 100000000] as $k => $v) {
            Setting::set($k, $v);
        }
        Setting::setEligibilityMinVolumes([0], 0);
        Setting::setLevelPercentages([10, 9, 8, 7, 6, 5, 4]);   // a different rate on every one of the seven levels
        Setting::set('cascade_depth', 7);

        $cat = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $p = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'essential', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $region->id]);
        $this->plan->products()->attach($p->id, ['qty' => 1]);
    }

    // ------------------------------------------------------------------------------------------ building the network

    private function join(string $name, ?Member $sponsor, ?string $side): Member
    {
        $this->app['auth']->guard('member')->logout();
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();
        $this->phone++;
        $phone = '90'.str_pad((string) $this->phone, 8, '0', STR_PAD_LEFT);

        $this->get(route('register', ['plan' => $this->plan->id] + ($sponsor ? ['sponsor' => $sponsor->member_code, 'side' => $side] : ['region' => 'MAIN'])))->assertOk();
        $this->post(route('register.send'), ['name' => $name, 'phone' => $phone] + ($sponsor ? [] : ['region' => 'MAIN', 'plan_id' => $this->plan->id]));
        $this->assertEmpty(session('errors')?->all() ?? [], $name);
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        return Member::where('phone', $phone)->firstOrFail();
    }

    private function pay(Member $m): Order
    {
        $order = Order::where('member_id', $m->id)->where('status', 'pending_payment')->firstOrFail();
        $this->actingAs($m, 'member')->post(route('member.orders.submit-payment', $order), ['method' => 'upi', 'reference' => 'UTR'.$order->id, 'billing_name' => $m->name, 'billing_phone' => $m->phone])->assertSessionHasNoErrors();
        $this->app['auth']->guard('member')->logout();
        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', Payment::where('order_id', $order->id)->firstOrFail()))->assertSessionHasNoErrors();
        $this->app['auth']->guard('web')->logout();

        return $order->fresh();
    }

    private function build(): void
    {
        $this->chain[0] = $this->join('Root Ravi', null, null);
        $this->pay($this->chain[0]);
        for ($k = 1; $k <= self::DEPTH; $k++) {
            $this->chain[$k] = $this->join("Chain L{$k}", $this->chain[$k - 1]->fresh(), 'left');
            $this->pay($this->chain[$k]);
        }
        for ($k = 0; $k < self::DEPTH; $k++) {
            $this->direct[$k] = $this->join("Direct D{$k}", $this->chain[$k]->fresh(), 'right');
            $this->pay($this->direct[$k]);
        }
    }

    private function cycle(): Cycle
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.cycles.store'), ['period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString()]);
        $cycle = Cycle::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', $cycle))->assertSessionHasNoErrors();

        return $cycle->fresh();
    }

    private function ledger(string $type, ?Member $m = null)
    {
        return LedgerEntry::where('type', $type)->when($m, fn ($q) => $q->where('member_id', $m->id));
    }

    private function sub(array $perms): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    // ------------------------------------------------------------------------------------------ every level

    public function test_matching_income_follows_the_seven_level_rates_up_the_placement_line_and_stops_at_level_seven(): void
    {
        $this->build();
        $this->cycle();
        $idOf = array_merge(array_map(fn ($m) => $m->id, $this->chain), []);
        $chainIndex = array_flip($idOf);                                     // member id => position in the chain

        $entries = $this->ledger('matching')->get();
        $this->assertGreaterThan(0, $entries->count());
        $pct = [1 => 10, 2 => 9, 3 => 8, 4 => 7, 5 => 6, 6 => 5, 7 => 4];
        $seenLevels = [];
        $bySource = [];

        foreach ($entries as $e) {
            $this->assertMatchesRegularExpression('/Level (\d+)\) from (\S+)/', $e->description);
            preg_match('/Level (\d+)\) from (\S+)/', $e->description, $m);
            $level = (int) $m[1];
            $source = Member::find($e->source_member_id);

            $this->assertLessThanOrEqual(7, $level, 'Nobody is paid beyond level 7');
            $this->assertSame($source->member_code, $m[2]);
            // The payee really is (level - 1) steps above the source in the placement line.
            $this->assertArrayHasKey($e->member_id, $chainIndex, 'Only chain members earn matching here');
            $this->assertSame($chainIndex[$e->source_member_id] - $chainIndex[$e->member_id] + 1, $level, "payee {$e->member_id} / source {$e->source_member_id}");
            $this->assertGreaterThan(0, (float) $e->amount);

            $seenLevels[$level] = true;
            $bySource[$e->source_member_id][$level] = (float) $e->amount;
        }
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys($seenLevels) === [1, 2, 3, 4, 5, 6, 7] ? [1, 2, 3, 4, 5, 6, 7] : collect($seenLevels)->keys()->sort()->values()->all(), 'All seven levels were paid');

        // Within one source the amounts keep the proportions of the rates (the same scaling applies to all of them).
        foreach ($bySource as $source => $levels) {
            foreach ($levels as $level => $amount) {
                if ($level === 1 || ! isset($levels[1])) {
                    continue;
                }
                $this->assertEqualsWithDelta($levels[1] * $pct[$level] / $pct[1], $amount, 0.02, "source {$source}, level {$level}");
            }
        }

        // The very top member is never paid from the deepest match: chain 8 has no children, so nothing flows from there,
        // and chain 7's match reaches only up to level 7 (chain 1), never chain 0.
        $this->assertSame(0, $this->ledger('matching', $this->chain[0])->where('description', 'like', '%from '.$this->chain[7]->member_code)->count());
        $this->assertGreaterThan(0, $this->ledger('matching', $this->chain[1])->where('description', 'like', 'Matching cascade (Level 7) from '.$this->chain[7]->member_code)->count());
    }

    public function test_the_level_report_adds_up_to_what_was_posted_and_total_payouts_stay_inside_the_pool(): void
    {
        $this->build();
        $cycle = $this->cycle();
        $net = app(NetworkService::class);

        foreach ([0, 1, 3] as $k) {
            $root = $this->chain[$k]->fresh();
            $levels = $net->levelBreakdown($root, 7, true);
            $fromReport = collect($levels)->sum('matching_income');
            $this->assertEqualsWithDelta((float) $this->ledger('matching', $root)->where('source_member_id', '!=', $root->id)->sum('amount'), $fromReport, 0.01, "chain {$k}: levels add up to the matching posted from people below");
            $this->assertEqualsWithDelta((float) $this->ledger('sponsor', $root)->sum('amount'), collect($levels)->sum('sponsor_income'), 0.01, "chain {$k}: sponsor income by level");
        }

        $paid = (float) LedgerEntry::whereIn('type', ['self', 'sponsor', 'matching', 'rank'])->sum('amount');
        $this->assertLessThanOrEqual((float) $cycle->pool_bv + 0.01, $paid, 'Everything paid out stays inside the pool');
        $this->assertEqualsWithDelta($paid, (float) $cycle->total_paid, 0.05);

        foreach (['admin.reports.levels', 'admin.reports.payouts', 'admin.reports.compliance', 'admin.reports.gst'] as $route) {
            $this->actingAs($this->admin, 'web')->get(route($route))->assertOk();
        }
        $this->actingAs($this->chain[0]->fresh(), 'member')->get(route('member.levels.index'))->assertOk();
        $this->actingAs($this->chain[0]->fresh(), 'member')->get(route('member.reports.index'))->assertOk();
    }

    // ------------------------------------------------------------------------------------------ refund and cancellation

    public function test_refunding_a_paid_order_before_the_cycle_removes_it_from_every_calculation(): void
    {
        $this->build();
        $d3 = $this->direct[3];
        $order = Order::where('member_id', $d3->id)->firstOrFail();
        $this->assertSame('active', $d3->fresh()->status);

        $this->actingAs($this->admin, 'web')->post(route('admin.orders.credit-note.store', $order), ['reason' => 'Customer asked for a refund', 'refund_method' => 'cash'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('inactive', $d3->fresh()->status);
        $this->assertCount(1, CreditNote::all());
        $this->actingAs($this->chain[3]->fresh(), 'member')->get(route('member.team.index'))->assertOk()->assertDontSee('Direct D3');

        $this->cycle();

        $this->assertSame(16, Order::where('status', 'settled')->count(), 'The refunded order is not settled into the cycle');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, LedgerEntry::where('order_id', $order->id)->count());
        $this->assertEqualsWithDelta(0.0, (float) $this->ledger('self', $d3)->sum('amount'), 0.001, 'The refunded buyer earns no self income');
        // Chain 3 sponsored chain 4 and Direct 3: only chain 4's order earns sponsor income now.
        $this->assertEqualsWithDelta(160.0, (float) $this->ledger('sponsor', $this->chain[3])->sum('amount'), 0.01);
        // With an empty right leg chain 3 has nothing to match, so no matching income starts from chain 3.
        $this->assertSame(0, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->chain[3]->id)->count());
        // Everyone else is untouched: chain 5 is still paid on its own match.
        $this->assertGreaterThan(0, LedgerEntry::where('type', 'matching')->where('source_member_id', $this->chain[5]->id)->count());
    }

    public function test_the_gst_register_nets_a_refund_to_nothing_for_that_order(): void
    {
        $this->build();
        $order = Order::where('member_id', $this->direct[0]->id)->firstOrFail();
        $before = $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst'))->viewData('totals');
        $this->actingAs($this->admin, 'web')->post(route('admin.orders.credit-note.store', $order), ['reason' => 'Refund', 'refund_method' => 'cash']);
        $after = $this->actingAs($this->admin, 'web')->get(route('admin.reports.gst'))->viewData('totals');

        $this->assertSame($before['invoices'], $after['invoices'], 'The invoice stays in the register');
        $this->assertSame(1, $after['credit_notes']);
        $this->assertEqualsWithDelta($before['net_taxable'] - 2000.0, $after['net_taxable'], 0.01);
        $this->assertEqualsWithDelta($before['net_gst'] - 240.0, $after['net_gst'], 0.01);
    }

    public function test_after_the_cycle_a_refund_is_refused_because_income_was_already_paid(): void
    {
        $this->build();
        $this->cycle();
        $order = Order::where('member_id', $this->direct[2]->id)->firstOrFail();

        $this->actingAs($this->admin, 'web')->post(route('admin.orders.credit-note.store', $order), ['reason' => 'Too late', 'refund_method' => 'cash'])->assertSessionHas('error');

        $this->assertSame('settled', $order->fresh()->status);
        $this->assertSame(0, CreditNote::count());
    }

    public function test_admin_can_cancel_an_unpaid_order_and_cannot_cancel_a_paid_or_settled_one_this_way(): void
    {
        $this->build();
        $unpaid = $this->join('Late Lata', $this->chain[8]->fresh(), 'left');
        $pending = Order::where('member_id', $unpaid->id)->firstOrFail();
        $paid = Order::where('member_id', $this->direct[1]->id)->firstOrFail();

        $this->actingAs($this->admin, 'web')->delete(route('admin.orders.cancel', $pending))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('cancelled', $pending->fresh()->status);

        // A paid order has an invoice: it must be reversed with a credit note, not quietly cancelled.
        $this->actingAs($this->admin, 'web')->delete(route('admin.orders.cancel', $paid))->assertSessionHas('error');
        $this->assertSame('paid', $paid->fresh()->status);

        $this->cycle();
        $this->actingAs($this->admin, 'web')->delete(route('admin.orders.cancel', $paid->fresh()))->assertSessionHas('error');
        $this->assertSame('settled', $paid->fresh()->status);
    }

    public function test_a_cancelled_order_cannot_be_paid_afterwards(): void
    {
        $this->build();
        $late = $this->join('Late Lata', $this->chain[8]->fresh(), 'left');
        $order = Order::where('member_id', $late->id)->firstOrFail();
        $this->actingAs($late, 'member')->post(route('member.orders.submit-payment', $order), ['method' => 'upi', 'reference' => 'UTRLATE', 'billing_name' => 'Late', 'billing_phone' => $late->phone]);
        $this->app['auth']->guard('member')->logout();

        $this->actingAs($this->admin, 'web')->delete(route('admin.orders.cancel', $order))->assertSessionHasNoErrors();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.payments.verify', $payment))->assertSessionHasErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('inactive', $late->fresh()->status);
    }

    public function test_who_may_cancel_and_who_may_refund(): void
    {
        $this->build();
        $unpaid = $this->join('Late Lata', $this->chain[8]->fresh(), 'left');
        $pending = Order::where('member_id', $unpaid->id)->firstOrFail();
        $paid = Order::where('member_id', $this->direct[4]->id)->firstOrFail();

        // Cancelling needs Orders access; refunding needs Finance too.
        $this->actingAs($this->sub(['members']), 'web')->delete(route('admin.orders.cancel', $pending))->assertForbidden();
        $this->actingAs($this->sub(['orders']), 'web')->post(route('admin.orders.credit-note.store', $paid), ['reason' => 'x', 'refund_method' => 'cash'])->assertForbidden();
        $this->actingAs($this->sub(['orders', 'finance']), 'web')->post(route('admin.orders.credit-note.store', $paid), ['reason' => 'Refund', 'refund_method' => 'cash'])->assertSessionHasNoErrors();
        $this->actingAs($this->sub(['orders']), 'web')->delete(route('admin.orders.cancel', $pending))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pending->fresh()->status);

        // A member can never reach the admin actions.
        $this->app['auth']->guard('web')->logout();
        $other = Order::where('member_id', $this->direct[5]->id)->firstOrFail();
        $this->actingAs($this->chain[5]->fresh(), 'member')->delete(route('admin.orders.cancel', $other))->assertRedirect(route('admin.login'));
        $this->assertSame('paid', $other->fresh()->status);
    }

    public function test_the_admin_order_list_shows_cancel_for_unpaid_and_the_credit_note_for_paid_orders(): void
    {
        $this->build();
        $unpaid = $this->join('Late Lata', $this->chain[8]->fresh(), 'left');
        $pending = Order::where('member_id', $unpaid->id)->firstOrFail();
        $paid = Order::where('member_id', $this->direct[6]->id)->firstOrFail();

        $res = $this->actingAs($this->admin, 'web')->get(route('admin.orders.index'))->assertOk();
        $res->assertSee('action="'.route('admin.orders.cancel', $pending).'"', false);
        $res->assertDontSee('action="'.route('admin.orders.cancel', $paid).'"', false);
        $res->assertSee('action="'.route('admin.orders.credit-note.store', $paid).'"', false);
    }

    // ------------------------------------------------------------------------------------------ withdrawals

    private function earner(float $amount = 1000): Member
    {
        $m = $this->chain[0]->fresh();
        $m->forceFill(['bank_account_name' => 'Root Ravi', 'bank_account_number' => '123456789012', 'bank_ifsc' => 'HDFC0001234', 'bank_name' => 'HDFC'])->save();
        LedgerEntry::create(['member_id' => $m->id, 'type' => 'sponsor', 'amount' => $amount, 'description' => 'test credit']);

        return $m->fresh();
    }

    private function request(Member $m, $amount)
    {
        return $this->actingAs($m->fresh(), 'member')->post(route('member.wallet.request'), ['amount' => $amount]);
    }

    private function balance(Member $m): float
    {
        return (float) $m->fresh()->availableBalance();
    }

    public function test_a_withdrawal_reserves_money_and_every_ending_puts_it_back_or_pays_it(): void
    {
        $this->build();
        $m = $this->earner(1000);
        $start = $this->balance($m);
        $this->assertEqualsWithDelta(1000.0, $start, 0.01);

        // Over the balance, zero, negative and junk are all refused; nothing is reserved.
        foreach ([$start + 1, 0, -5, 'abc', ''] as $bad) {
            $this->request($m, $bad)->assertSessionHasErrors();
        }
        $this->assertSame(0, Withdrawal::count());

        // Two requests reserve their amounts, so a third that would exceed the rest is refused.
        $this->request($m, 400)->assertSessionHasNoErrors();
        $this->request($m, 500)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(100.0, $this->balance($m), 0.01);
        $this->request($m, 200)->assertSessionHasErrors();
        $this->assertSame(2, Withdrawal::count());

        [$w1, $w2] = Withdrawal::orderBy('id')->get()->all();

        // Rejecting a pending request gives the money back.
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.reject', $w1))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(500.0, $this->balance($m), 0.01);

        // Approved money stays reserved; rejecting an approved one releases it too.
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w2))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(500.0, $this->balance($m), 0.01);
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.reject', $w2))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1000.0, $this->balance($m), 0.01);

        // A rejected request cannot be approved or paid afterwards.
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w2))->assertSessionHasErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w2), ['payout_reference' => 'R1'])->assertSessionHasErrors();
        $this->assertSame('rejected', $w2->fresh()->status);
    }

    public function test_a_paid_withdrawal_takes_the_money_once_and_cannot_be_undone_or_repeated(): void
    {
        $this->build();
        $m = $this->earner(1000);
        $this->request($m, 600)->assertSessionHasNoErrors();
        $w = Withdrawal::firstOrFail();

        // Cannot be paid before it is approved, nor without a reference.
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'EARLY'])->assertSessionHasErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasNoErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => ''])->assertSessionHasErrors();
        $this->assertSame('approved', $w->fresh()->status);

        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'NEFT42'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertSame('NEFT42', $w->fresh()->payout_reference);
        $this->assertEqualsWithDelta(400.0, $this->balance($m), 0.01);
        $this->assertSame(1, LedgerEntry::where('type', 'withdrawal')->where('withdrawal_id', $w->id)->count());

        // Paid is final: no second payment, no rejection, no re-approval.
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w->fresh()), ['payout_reference' => 'AGAIN'])->assertSessionHasErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.reject', $w->fresh()))->assertSessionHasErrors();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w->fresh()))->assertSessionHasErrors();
        $this->assertSame(1, LedgerEntry::where('type', 'withdrawal')->count(), 'Debited once');
        $this->assertEqualsWithDelta(400.0, $this->balance($m), 0.01);
    }

    public function test_a_withdrawal_needs_bank_details_and_a_sub_admin_needs_the_withdrawals_permission(): void
    {
        $this->build();
        $m = $this->chain[0]->fresh();
        LedgerEntry::create(['member_id' => $m->id, 'type' => 'sponsor', 'amount' => 500, 'description' => 'credit']);

        $this->request($m, 100)->assertSessionHasErrors();                  // no bank details yet
        $m = $this->earner(0);
        $this->request($m, 100)->assertSessionHasNoErrors();
        $w = Withdrawal::firstOrFail();

        $this->actingAs($this->sub(['orders']), 'web')->put(route('admin.withdrawals.approve', $w))->assertForbidden();
        $this->actingAs($this->sub(['withdrawals']), 'web')->put(route('admin.withdrawals.approve', $w))->assertSessionHasNoErrors();
        $this->assertSame('approved', $w->fresh()->status);
    }

    public function test_a_member_cannot_withdraw_money_they_have_not_earned_even_with_an_unpaid_account(): void
    {
        $this->build();
        $late = $this->join('Late Lata', $this->chain[8]->fresh(), 'left');

        $this->actingAs($late, 'member')->post(route('member.wallet.request'), ['amount' => 10])->assertRedirect(route('member.plans.index'));
        $this->assertSame(0, Withdrawal::count());
    }

    public function test_the_emails_for_every_withdrawal_step_were_sent(): void
    {
        $this->build();
        Setting::set('site_contact_email', 'office@beyondsure.test');
        $m = $this->earner(1000);
        $m->forceFill(['email' => 'ravi@example.com'])->save();
        $this->request($m, 300);
        $w = Withdrawal::firstOrFail();
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.approve', $w));
        $this->actingAs($this->admin, 'web')->put(route('admin.withdrawals.mark-paid', $w), ['payout_reference' => 'NEFT9']);

        $subjects = Mail::sent(BrandedMail::class)->filter(fn ($x) => $x->hasTo('ravi@example.com'))->pluck('mailSubject')->implode(' | ');
        foreach (['Withdrawal request received', 'Withdrawal approved', 'Withdrawal paid'] as $s) {
            $this->assertStringContainsString($s, $subjects);
        }
        $this->assertSame(1, Mail::sent(BrandedMail::class)->filter(fn ($x) => $x->hasTo('office@beyondsure.test') && str_contains($x->mailSubject, 'Withdrawal request'))->count());
    }
}
