<?php

namespace Tests\Feature;

use App\Models\Category;
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

/** The checkout page: where am I in the process, how much, who do I pay and what happens next. */
class PayPageTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $region = Region::where('code', 'MAIN')->firstOrFail();
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $region->id]);
        $plan->products()->attach($prod->id, ['qty' => 1]);
        $this->member = Member::create(['member_code' => 'PP1', 'name' => 'Pay Person', 'phone' => '9800000001', 'status' => 'inactive', 'region_id' => $region->id]);
        $this->order = Order::create(['order_code' => 'ORD-PP1', 'member_id' => $this->member->id, 'plan_id' => $plan->id, 'amount' => 2240, 'taxable_amount' => 2000, 'gst_amount' => 240, 'bv' => 800, 'status' => 'pending_payment']);
    }

    private function pay()
    {
        return $this->actingAs($this->member, 'member')->get(route('member.orders.pay', $this->order));
    }

    private ?User $adminUser = null;

    private function admin(): User
    {
        return $this->adminUser ??= User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function sub(array $perms): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);
    }

    // ------------------------------------------------------------------------------------------ progress + summary

    public function test_a_progress_bar_shows_where_the_customer_is_in_the_four_steps(): void
    {
        $this->pay()->assertOk()
            ->assertSee('Choose plan')->assertSee('Pay')->assertSee('Approval')->assertSee('Active')
            ->assertSee('data-step="pay" data-state="current"', false)
            ->assertSee('data-step="plan" data-state="done"', false)
            ->assertSee('data-step="approval" data-state="todo"', false);
    }

    public function test_after_a_payment_is_sent_the_bar_moves_to_approval_and_says_what_happens_next(): void
    {
        Payment::create(['order_id' => $this->order->id, 'method' => 'upi', 'reference' => 'UTR1', 'status' => 'pending']);

        $this->pay()->assertOk()
            ->assertSee('data-step="pay" data-state="done"', false)
            ->assertSee('data-step="approval" data-state="current"', false)
            ->assertSee('We have your payment')->assertSee('checking')
            ->assertSee('Your wallet, team and earnings open')
            ->assertDontSee('Submit payment for verification');
    }

    public function test_a_rejected_payment_explains_it_and_does_not_leave_the_customer_stuck(): void
    {
        Payment::create(['order_id' => $this->order->id, 'method' => 'upi', 'reference' => 'UTR1', 'status' => 'rejected']);

        // The payment form comes back so they can send the right reference, and the bar returns to the Pay step.
        $this->pay()->assertOk()
            ->assertSee('could not be verified')
            ->assertSee('Submit payment for verification')
            ->assertSee('data-step="pay" data-state="current"', false)
            ->assertSee('data-step="approval" data-state="todo"', false);

        // ...and it really can be sent again.
        $this->actingAs($this->member, 'member')->post(route('member.orders.submit-payment', $this->order), [
            'method' => 'upi', 'reference' => 'UTR-RIGHT', 'billing_name' => 'Pay Person', 'billing_phone' => '9800000001',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->order->fresh()->payment->status);
    }

    public function test_the_amount_due_is_shown_big_with_the_gst_breakdown(): void
    {
        $this->pay()->assertOk()->assertSee('Amount to pay')->assertSee('₹2,240')->assertSee('incl. GST')->assertSee('₹240.00');
    }

    // ------------------------------------------------------------------------------------------ company payment details

    public function test_the_company_payment_details_are_shown_with_copy_buttons(): void
    {
        Setting::set('pay_upi_id', 'beyondsure@upi');
        Setting::set('pay_account_name', 'BeyondSure Wellness Pvt Ltd');
        Setting::set('pay_bank_name', 'HDFC Bank');
        Setting::set('pay_account_number', '50200012345678');
        Setting::set('pay_ifsc', 'HDFC0001234');
        Setting::set('pay_note', 'Write your mobile number in the remarks.');

        $this->pay()->assertOk()
            ->assertSee('beyondsure@upi')->assertSee('BeyondSure Wellness Pvt Ltd')->assertSee('HDFC Bank')
            ->assertSee('50200012345678')->assertSee('HDFC0001234')->assertSee('Write your mobile number in the remarks.')
            ->assertSee('data-copy-text="beyondsure@upi"', false)->assertSee('data-copy-text="50200012345678"', false);
    }

    public function test_without_payment_details_the_page_says_how_to_get_them_instead_of_showing_a_blank(): void
    {
        Setting::set('site_contact_phone', '+91 99999 11111');

        $this->pay()->assertOk()->assertSee('not set up yet')->assertSee('+91 99999 11111');
    }

    public function test_payment_details_are_escaped_on_the_page(): void
    {
        Setting::set('pay_account_name', '<script>alert(1)</script>');
        Setting::set('pay_note', '<img src=x onerror=alert(2)>');

        $this->pay()->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }

    // ------------------------------------------------------------------------------------------ who may set them

    private function fields(array $extra = []): array
    {
        return $extra + ['site_name' => 'BeyondSure+'];
    }

    public function test_the_super_admin_can_save_the_payment_details_from_website_settings(): void
    {
        $this->actingAs($this->admin(), 'web')->put(route('admin.site-content.update'), $this->fields([
            'pay_upi_id' => 'beyondsure@upi', 'pay_account_name' => 'BeyondSure Wellness', 'pay_bank_name' => 'HDFC Bank',
            'pay_account_number' => '50200012345678', 'pay_ifsc' => 'hdfc0001234', 'pay_note' => 'Add your mobile number.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('beyondsure@upi', Setting::get('pay_upi_id'));
        $this->assertSame('HDFC0001234', Setting::get('pay_ifsc'), 'IFSC is stored in capitals');
        $this->actingAs($this->admin(), 'web')->get(route('admin.site-content.edit'))->assertOk()->assertSee('Payment details')->assertSee('beyondsure@upi');
    }

    public function test_bad_payment_details_are_refused(): void
    {
        $bad = [
            ['pay_upi_id' => 'not a upi id'], ['pay_upi_id' => 'a@b c'],
            ['pay_account_number' => '12ab34'], ['pay_account_number' => '123'],
            ['pay_ifsc' => 'HDFC1234567'], ['pay_ifsc' => 'XYZ'],
        ];

        foreach ($bad as $fields) {
            $this->actingAs($this->admin(), 'web')->put(route('admin.site-content.update'), $this->fields($fields))->assertSessionHasErrors(array_key_first($fields));
        }
    }

    public function test_staff_without_finance_access_can_neither_see_nor_change_the_payment_details(): void
    {
        Setting::set('pay_upi_id', 'beyondsure@upi');
        $content = $this->sub(['content']);

        $this->actingAs($content, 'web')->get(route('admin.site-content.edit'))->assertOk()
            ->assertDontSee('Payment details')->assertDontSee('beyondsure@upi');

        // Posting the fields directly is ignored: nobody without finance access can point members' money elsewhere.
        $this->actingAs($content, 'web')->put(route('admin.site-content.update'), $this->fields(['pay_upi_id' => 'attacker@upi', 'site_footer_about' => 'New footer']))->assertSessionHasNoErrors();
        $this->assertSame('beyondsure@upi', Setting::get('pay_upi_id'));
        $this->assertSame('New footer', Setting::get('site_footer_about'), 'Their other website edits still work');
    }

    public function test_saving_website_settings_without_the_payment_fields_does_not_wipe_them_for_a_non_finance_user(): void
    {
        Setting::set('pay_account_number', '50200012345678');

        $this->actingAs($this->sub(['content']), 'web')->put(route('admin.site-content.update'), $this->fields())->assertSessionHasNoErrors();

        $this->assertSame('50200012345678', Setting::get('pay_account_number'));
    }

    public function test_another_members_order_is_still_private(): void
    {
        $other = Member::create(['member_code' => 'PP2', 'name' => 'Someone Else', 'phone' => '9800000002', 'status' => 'inactive', 'region_id' => $this->member->region_id]);

        $this->actingAs($other, 'member')->get(route('member.orders.pay', $this->order))->assertForbidden();
    }
}
