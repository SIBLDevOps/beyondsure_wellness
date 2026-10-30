<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Priya Shah', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function sub(array $perms): User
    {
        return User::create(['name' => 'Sub Person', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    private function member(string $code): Member
    {
        return Member::create(['member_code' => $code, 'name' => $code, 'phone' => '9'.random_int(100000000, 999999999), 'status' => 'inactive']);
    }

    private function plan(): Plan
    {
        $cat = Category::firstOrCreate(['slug' => 'c'], ['name' => 'C', 'is_free' => false]);
        $prod = Product::firstOrCreate(['sku' => 'P1'], ['category_id' => $cat->id, 'name' => 'P', 'cost_price' => 10, 'sell_price' => 100, 'gst_pct' => 12, 'is_active' => true]);
        $plan = Plan::create(['name' => 'Plan', 'slug' => 'plan'.random_int(1, 99999), 'price' => 1000, 'bv' => 400, 'is_active' => true]);
        $plan->products()->attach($prod->id, ['qty' => 1]);

        return $plan;
    }

    private function order(Member $m, string $status): Order
    {
        return Order::create(['order_code' => 'O'.random_int(100000, 999999), 'member_id' => $m->id, 'plan_id' => $this->plan()->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => $status]);
    }

    private function page(?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->get(route('admin.dashboard'));
    }

    public function test_the_page_greets_the_person_by_name(): void
    {
        $this->page()->assertOk()->assertSee('Priya')->assertSeeInOrder(['Good', 'Priya']);
    }

    public function test_a_clear_to_do_list_shows_what_needs_doing_with_plain_sentences_and_buttons(): void
    {
        $m = $this->member('M1');
        $paid = $this->order($m, 'paid');                       // waiting for a cycle
        $pending = $this->order($m, 'pending_payment');
        Payment::create(['order_id' => $pending->id, 'method' => 'upi', 'reference' => 'U1', 'status' => 'pending']);
        Withdrawal::create(['member_id' => $m->id, 'amount' => 100, 'status' => 'pending']);
        Withdrawal::create(['member_id' => $m->id, 'amount' => 200, 'status' => 'approved']);
        ContactMessage::create(['name' => 'X', 'email' => 'x@x.test', 'message' => 'hello']);

        $res = $this->page()->assertOk()->assertSee('What needs you today')
            ->assertSee('1 payment is waiting for you to check')
            ->assertSee('1 paid order is waiting for a cycle')
            ->assertSee('1 withdrawal request needs approval')
            ->assertSee('1 approved withdrawal is ready to pay')
            ->assertSee('1 new message')
            ->assertSee(route('admin.payments.index'), false)
            ->assertSee(route('admin.cycles.index'), false)
            ->assertSee(route('admin.withdrawals.index'), false)
            ->assertSee(route('admin.dispatch.index'), false)
            ->assertSee(route('admin.messages.index'), false);

        $this->assertNotNull($paid);
        $res->assertDontSee("You're all caught up", false);
    }

    public function test_when_nothing_is_waiting_it_says_all_caught_up(): void
    {
        $this->page()->assertOk()->assertSee("You're all caught up", false);
    }

    public function test_members_who_have_not_bought_a_plan_are_listed_with_a_filtered_link(): void
    {
        $this->member('NOBUY1');
        $this->member('NOBUY2');

        $this->page()->assertSee('2 members have not bought a plan yet')
            ->assertSee(route('admin.members.index', ['onboarding' => 'no_purchase']), false);
    }

    public function test_a_sub_admin_only_sees_to_do_items_for_sections_they_can_open(): void
    {
        $m = $this->member('M2');
        $pending = $this->order($m, 'pending_payment');
        Payment::create(['order_id' => $pending->id, 'method' => 'upi', 'reference' => 'U2', 'status' => 'pending']);
        Withdrawal::create(['member_id' => $m->id, 'amount' => 100, 'status' => 'pending']);

        $html = $this->page($this->sub(['orders']))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('admin.payments.index'), $html);
        $this->assertStringNotContainsString(route('admin.withdrawals.index'), $html);
        $this->assertStringNotContainsString(route('admin.cycles.index'), $html);
        $this->assertStringNotContainsString('waiting for you to check', $html);

        $this->page($this->sub(['payments']))->assertSee('1 payment is waiting for you to check')->assertSee(route('admin.payments.index'), false);
    }

    public function test_a_new_install_shows_a_setup_guide_that_ticks_off_as_things_are_done(): void
    {
        $this->page()->assertSee('Set up your business')->assertSee('Add your products')->assertSee('Create your plans')->assertSee('Share your first-member link')->assertSee('0 of 4');

        $plan = $this->plan();      // adds a product and a plan
        $this->member('FIRST');

        $res = $this->page()->assertOk();
        $res->assertDontSee('Set up your business');
        $this->assertNotNull($plan);
    }

    public function test_the_setup_guide_warns_about_products_without_a_gst_rate(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c', 'is_free' => false]);
        Product::create(['category_id' => $cat->id, 'name' => 'No GST', 'sku' => 'N1', 'cost_price' => 10, 'sell_price' => 100, 'gst_pct' => null, 'is_active' => true]);

        $this->page()->assertSee('1 product has no GST rate');
    }

    public function test_the_money_figures_and_finance_shortcuts_still_need_finance_access(): void
    {
        $this->page()->assertSee('Commissions This Month')->assertSee('Compensation Rules')->assertSee(route('admin.team.index'), false);

        $this->page($this->sub(['orders']))->assertOk()
            ->assertDontSee('Commissions Posted Today')->assertDontSee('Commissions This Month')->assertDontSee('Compensation Rules')->assertDontSee('Level-Wise Distribution');
    }

    public function test_shortcuts_only_offer_places_the_person_may_open(): void
    {
        $html = $this->page($this->sub(['members']))->assertOk()->assertSee('Quick actions')->getContent();

        $this->assertStringContainsString(route('admin.members.index'), $html);
        $this->assertStringNotContainsString(route('admin.team.index'), $html);
        $this->assertStringNotContainsString(route('admin.settings.edit'), $html);
    }

    public function test_the_page_still_works_for_a_sub_admin_with_no_permissions(): void
    {
        $this->page($this->sub([]))->assertOk()->assertSee('Sub Person');
    }
}
