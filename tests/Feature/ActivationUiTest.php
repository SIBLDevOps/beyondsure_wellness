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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The admin "Pending activation" page as a working pipeline: tabs, urgency, amounts, age, and who to chase first. */
class ActivationUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plan $plan;

    private Region $main;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->main = Region::where('code', 'MAIN')->firstOrFail();
        $cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
        $prod = Product::create(['category_id' => $cat->id, 'name' => 'Syrup', 'sku' => 'S1', 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
        $this->plan = Plan::create(['name' => 'Essential', 'slug' => 'e', 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->main->id]);
        $this->plan->products()->attach($prod->id, ['qty' => 1]);
    }

    private function person(string $name, string $stage = 'registered', int $daysAgo = 0, bool $withPlan = true): Member
    {
        $this->n++;
        $m = Member::create([
            'member_code' => 'UI'.$this->n, 'name' => $name, 'phone' => '92'.str_pad((string) $this->n, 8, '0', STR_PAD_LEFT), 'status' => 'inactive',
            'region_id' => $this->main->id, 'interested_plan_id' => $withPlan ? $this->plan->id : null,
        ]);
        $m->forceFill(['created_at' => now()->subDays($daysAgo)->subMinutes(5)])->saveQuietly();

        if ($stage !== 'registered') {
            $order = Order::create(['order_code' => 'O'.$this->n.random_int(1000, 9999), 'member_id' => $m->id, 'plan_id' => $this->plan->id, 'amount' => 2240, 'taxable_amount' => 2000, 'gst_amount' => 240, 'bv' => 800, 'status' => 'pending_payment']);
            if ($stage === 'payment_submitted') {
                Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'U'.$this->n, 'status' => 'pending']);
            }
        }

        return $m;
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin, 'web')->get(route('admin.activation.index', $query));
    }

    // ------------------------------------------------------------------------------------------ the pipeline tabs

    public function test_there_is_a_tab_for_everyone_and_one_per_step_each_with_its_count(): void
    {
        $this->person('A', 'registered');
        $this->person('B', 'registered');
        $this->person('C', 'order_placed');
        $this->person('D', 'payment_submitted');

        $res = $this->page()->assertOk();
        foreach (['all' => 4, 'registered' => 2, 'order_placed' => 1, 'payment_submitted' => 1] as $tab => $count) {
            $res->assertSee('data-tab="'.$tab.'"', false);
        }
        $counts = $res->viewData('counts');
        $this->assertSame([4, 2, 1, 1], [$counts['total'], $counts['registered'], $counts['order_placed'], $counts['payment_submitted']]);
    }

    public function test_the_current_tab_is_marked_and_the_others_are_not(): void
    {
        $this->person('A');

        $this->page(['stage' => 'order_placed'])->assertSee('data-tab="order_placed" aria-current="page"', false)->assertDontSee('data-tab="registered" aria-current="page"', false);
        $this->page()->assertSee('data-tab="all" aria-current="page"', false);
    }

    public function test_each_step_tab_explains_what_to_do_about_it(): void
    {
        $this->person('A');

        $this->page()->assertOk()->assertSee('Needs a nudge')->assertSee('Waiting for payment')->assertSee('Verify now');
    }

    // ------------------------------------------------------------------------------------------ who has waited too long

    public function test_people_waiting_three_or_more_days_are_counted_and_can_be_filtered(): void
    {
        $this->person('Fresh Fay', 'registered', 0);
        $this->person('Old Olga', 'registered', 5);
        $this->person('Older Omar', 'order_placed', 9);

        $res = $this->page()->assertOk();
        $this->assertSame(2, $res->viewData('counts')['stale']);
        $res->assertSee('data-tab="stale"', false)->assertSee('Waiting 3+ days');

        $this->page(['stale' => 1])->assertOk()->assertSee('Old Olga')->assertSee('Older Omar')->assertDontSee('Fresh Fay');
    }

    public function test_the_stale_filter_combines_with_a_step_and_with_search(): void
    {
        $this->person('Old Olga', 'registered', 5);
        $this->person('Older Omar', 'order_placed', 9);
        $this->person('Fresh Fay', 'order_placed', 0);

        $this->page(['stale' => 1, 'stage' => 'order_placed'])->assertSee('Older Omar')->assertDontSee('Old Olga')->assertDontSee('Fresh Fay');
        $this->page(['stale' => 1, 'search' => 'olga'])->assertSee('Old Olga')->assertDontSee('Older Omar');
    }

    public function test_the_count_of_old_waiters_ignores_the_current_filter(): void
    {
        $this->person('Old Olga', 'registered', 5);
        $this->person('Fresh Fay', 'registered', 0);

        $this->assertSame(1, $this->page(['search' => 'fay'])->viewData('counts')['stale']);
    }

    public function test_how_long_someone_has_waited_is_colour_coded(): void
    {
        $this->person('Fresh Fay', 'registered', 0);
        $this->person('Aging Ann', 'registered', 2);
        $this->person('Stale Sam', 'registered', 6);

        $this->page()->assertSee('data-age="fresh"', false)->assertSee('data-age="aging"', false)->assertSee('data-age="stale"', false);
    }

    // ------------------------------------------------------------------------------------------ what needs the admin right now

    public function test_a_banner_says_how_many_payments_are_waiting_to_be_verified(): void
    {
        $this->person('A', 'payment_submitted');
        $this->person('B', 'payment_submitted');

        $this->page()->assertOk()->assertSee('2 payments are waiting for you to verify')->assertSee(route('admin.payments.index'), false);
    }

    public function test_the_banner_uses_the_singular_and_is_absent_when_nothing_is_waiting(): void
    {
        $this->person('A', 'payment_submitted');
        $this->page()->assertSee('1 payment is waiting for you to verify');

        DB::table('payments')->delete();
        $this->page()->assertDontSee('waiting for you to verify');
    }

    public function test_staff_who_cannot_verify_payments_see_no_banner_button(): void
    {
        $this->person('A', 'payment_submitted');
        $members = User::create(['name' => 'M', 'email' => 'm@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);

        $this->actingAs($members, 'web')->get(route('admin.activation.index'))->assertOk()->assertDontSee('waiting for you to verify')->assertDontSee('Verify payment');
    }

    // ------------------------------------------------------------------------------------------ each person at a glance

    public function test_the_amount_to_be_paid_is_shown_from_the_order_or_the_plan_they_chose(): void
    {
        $this->person('Has Order', 'order_placed');
        $this->person('Chose Plan', 'registered');
        $this->person('No Plan', 'registered', 0, false);

        $res = $this->page()->assertOk()->assertSee('₹2,240');
        $byName = $res->viewData('people')->keyBy('name');
        $this->assertEqualsWithDelta(2240.0, (float) $byName['Has Order']->amount, 0.01);
        $this->assertEqualsWithDelta(2240.0, (float) $byName['Chose Plan']->amount, 0.01, 'Plan price 2,000 plus 12% GST');
        $this->assertNull($byName['No Plan']->amount);
    }

    public function test_each_person_shows_a_progress_marker_for_their_step(): void
    {
        $this->person('A', 'registered');
        $this->person('B', 'order_placed');
        $this->person('C', 'payment_submitted');

        $this->page()->assertSee('data-progress="1"', false)->assertSee('data-progress="2"', false)->assertSee('data-progress="3"', false);
    }

    public function test_the_main_action_follows_the_step(): void
    {
        $this->person('Reg Rita', 'registered');
        $this->person('Pay Paul', 'payment_submitted');

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('Verify payment', $html);
        $this->assertStringContainsString('Remind', $html);
        $this->assertSame(1, substr_count($html, 'data-action="verify"'), 'Only the person who paid gets a Verify button');
        $this->assertSame(1, substr_count($html, 'data-action="remind"'));
    }

    // ------------------------------------------------------------------------------------------ filters feel quick

    public function test_choosing_a_region_or_an_order_submits_straight_away(): void
    {
        Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->person('A');

        $this->page()->assertOk()->assertSee('onchange="this.form.submit()"', false);
    }

    public function test_clearing_the_filters_is_one_click_and_keeps_nothing(): void
    {
        $this->person('A');

        $this->page(['search' => 'zzz', 'stale' => 1])->assertOk()->assertSee('No one matches')->assertSee(route('admin.activation.index'), false);
    }

    public function test_pagination_links_keep_the_waiting_filter(): void
    {
        foreach (range(1, 30) as $i) {
            $this->person('Old '.$i, 'registered', 5);
        }

        $this->page(['stale' => 1])->assertOk()->assertSee('stale=1', false)->assertSee('page=2', false);
    }

    // ------------------------------------------------------------------------------------------ safety and speed

    public function test_names_are_escaped(): void
    {
        $this->person('<script>alert(1)</script>');

        $this->page()->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_people(): void
    {
        $count = function (): int {
            $this->page();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->page()->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        foreach (range(1, 4) as $i) {
            $this->person('P'.$i, $i % 2 ? 'order_placed' : 'registered', $i);
        }
        $few = $count();
        foreach (range(5, 24) as $i) {
            $this->person('P'.$i, $i % 3 ? 'order_placed' : 'payment_submitted', $i);
        }

        $this->assertSame($few, $count(), 'No query per person');
    }
}
