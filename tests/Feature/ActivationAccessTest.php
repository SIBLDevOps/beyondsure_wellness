<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sub-admins see "Pending activation" for their own regions, if they have Members or Payments access. */
class ActivationAccessTest extends TestCase
{
    use RefreshDatabase;

    private Region $main;

    private Region $gj;

    protected function setUp(): void
    {
        parent::setUp();
        $this->main = Region::where('code', 'MAIN')->firstOrFail();
        $this->gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);

        $this->person('Main Reg', $this->main);
        $this->person('Main Pay', $this->main, true);
        $this->person('Guj Reg', $this->gj);
        $this->person('Guj Pay', $this->gj, true);
    }

    private function person(string $name, Region $region, bool $paid = false): Member
    {
        static $n = 0;
        $n++;
        $m = Member::create(['member_code' => 'A'.$n, 'name' => $name, 'phone' => '98'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive', 'region_id' => $region->id]);
        if ($paid) {
            $plan = Plan::create(['name' => 'P'.$n, 'slug' => 'p'.$n, 'price' => 1000, 'bv' => 400, 'is_active' => true, 'region_id' => $region->id]);
            $order = Order::create(['order_code' => 'O'.$n, 'member_id' => $m->id, 'plan_id' => $plan->id, 'amount' => 1120, 'taxable_amount' => 1000, 'gst_amount' => 120, 'bv' => 400, 'status' => 'pending_payment']);
            Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'U'.$n, 'status' => 'pending']);
        }

        return $m;
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function open(User $u)
    {
        return $this->actingAs($u, 'web')->get(route('admin.activation.index'));
    }

    public function test_a_members_access_sub_admin_sees_only_their_regions_people_and_counts(): void
    {
        $res = $this->open($this->sub(['members'], [$this->gj->id]))->assertOk()
            ->assertSee('Guj Reg')->assertSee('Guj Pay')->assertDontSee('Main Reg')->assertDontSee('Main Pay');

        $counts = $res->viewData('counts');
        $this->assertSame([1, 0, 1, 2], [$counts['registered'], $counts['order_placed'], $counts['payment_submitted'], $counts['total']]);
    }

    public function test_a_payments_access_sub_admin_can_also_open_the_page_for_their_region(): void
    {
        $res = $this->open($this->sub(['payments'], [$this->main->id]))->assertOk()
            ->assertSee('Main Pay')->assertSee('Main Reg')->assertDontSee('Guj Pay');

        $res->assertSee('Verify payment');                                      // they may verify, so the button shows
        $res->assertSee(route('admin.payments.index'), false);
    }

    public function test_a_members_only_sub_admin_gets_no_verify_button_because_that_needs_payments_access(): void
    {
        $this->open($this->sub(['members'], [$this->main->id]))->assertOk()
            ->assertSee('Main Pay')->assertDontSee('Verify payment')->assertDontSee('Verify 1 payment');
    }

    public function test_a_sub_admin_with_neither_permission_is_refused_and_has_no_menu_link(): void
    {
        $orders = $this->sub(['orders', 'catalog']);

        $this->open($orders)->assertForbidden();
        $this->actingAs($orders, 'web')->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.activation.index'), false);
    }

    public function test_the_menu_link_and_its_count_follow_the_sub_admins_region_and_access(): void
    {
        $payer = $this->sub(['payments'], [$this->gj->id]);

        $html = $this->actingAs($payer, 'web')->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.activation.index'), false)->getContent();

        preg_match('/Pending activation<\/span>\s*<span class="badge[^>]*>(\d+)</', $html, $m);
        $this->assertSame('2', $m[1] ?? null, 'The badge counts only Gujarat\'s two people, not all four');
    }

    public function test_an_unrestricted_sub_admin_sees_every_region_and_the_super_admin_still_does(): void
    {
        $this->open($this->sub(['members'], null))->assertOk()->assertSee('Main Reg')->assertSee('Guj Reg');

        $admin = User::create(['name' => 'Admin', 'email' => 'root@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->open($admin)->assertOk()->assertSee('Main Pay')->assertSee('Guj Pay');
    }

    public function test_the_permission_form_tells_the_owner_that_these_permissions_open_the_page(): void
    {
        $this->assertStringContainsString('Pending activation', AdminPermissions::GROUPS['Operations']['members'][1]);
        $this->assertStringContainsString('Pending activation', AdminPermissions::GROUPS['Operations']['payments'][1]);
    }

    public function test_the_other_member_pages_still_need_the_members_permission_only(): void
    {
        $payer = $this->sub(['payments']);

        $this->actingAs($payer, 'web')->get(route('admin.members.index'))->assertForbidden();
        $this->actingAs($payer, 'web')->get(route('admin.orders.index'))->assertForbidden();
    }
}
