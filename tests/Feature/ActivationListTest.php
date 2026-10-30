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

/** The admin "Pending activation" page has to stay usable with hundreds of people: filter, search, page. */
class ActivationListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Region $main;

    private Plan $plan;

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

    /** @param 'registered'|'order_placed'|'payment_submitted' $stage */
    private function person(string $stage = 'registered', ?string $name = null, ?Region $region = null, ?string $phone = null, ?string $created = null): Member
    {
        $this->n++;
        $m = Member::create([
            'member_code' => 'P'.str_pad((string) $this->n, 4, '0', STR_PAD_LEFT), 'name' => $name ?? 'Person '.str_pad((string) $this->n, 3, '0', STR_PAD_LEFT),
            'phone' => $phone ?? '9'.str_pad((string) (100000000 + $this->n), 9, '0', STR_PAD_LEFT), 'status' => 'inactive',
            'region_id' => ($region ?? $this->main)->id, 'interested_plan_id' => $this->plan->id,
        ]);
        if ($created) {
            $m->forceFill(['created_at' => $created])->saveQuietly();
        }
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

    public function test_a_hundred_people_are_shown_a_page_at_a_time(): void
    {
        foreach (range(1, 60) as $i) {
            $this->person('registered');
        }

        $res = $this->page()->assertOk();
        $people = $res->viewData('people');
        $this->assertSame(60, $people->total());
        $this->assertSame(25, $people->count());
        $res->assertSee('Showing 1');

        $this->assertSame(10, $this->page(['page' => 3])->viewData('people')->count());
    }

    public function test_the_counts_cover_everybody_not_just_the_current_page_or_filter(): void
    {
        foreach (range(1, 30) as $i) {
            $this->person('registered');
        }
        foreach (range(1, 7) as $i) {
            $this->person('order_placed');
        }
        foreach (range(1, 4) as $i) {
            $this->person('payment_submitted');
        }

        foreach ([[], ['stage' => 'order_placed'], ['search' => 'nobody']] as $q) {
            $counts = $this->page($q)->viewData('counts');
            $this->assertSame([30, 7, 4, 41], [$counts['registered'], $counts['order_placed'], $counts['payment_submitted'], $counts['total']], json_encode($q));
        }
    }

    public function test_the_stage_filter_shows_only_that_step(): void
    {
        $this->person('registered', 'Reg One');
        $this->person('order_placed', 'Ord One');
        $this->person('payment_submitted', 'Pay One');

        $this->page(['stage' => 'payment_submitted'])->assertSee('Pay One')->assertDontSee('Reg One')->assertDontSee('Ord One');
        $this->page(['stage' => 'order_placed'])->assertSee('Ord One')->assertDontSee('Reg One')->assertDontSee('Pay One');
        $this->page(['stage' => 'registered'])->assertSee('Reg One')->assertDontSee('Ord One')->assertDontSee('Pay One');
        $this->page(['stage' => 'all'])->assertSee('Reg One')->assertSee('Ord One')->assertSee('Pay One');
    }

    public function test_a_rejected_payment_counts_as_order_placed_in_the_filter_too(): void
    {
        $m = $this->person('payment_submitted', 'Was Rejected');
        Payment::where('order_id', $m->orders()->first()->id)->update(['status' => 'rejected']);

        $this->page(['stage' => 'order_placed'])->assertSee('Was Rejected');
        $this->page(['stage' => 'payment_submitted'])->assertDontSee('Was Rejected');
    }

    public function test_an_unknown_stage_is_ignored_and_shows_everyone(): void
    {
        $this->person('registered', 'Reg One');
        $this->person('order_placed', 'Ord One');

        $this->page(['stage' => 'bogus'])->assertOk()->assertSee('Reg One')->assertSee('Ord One');
        $this->page(['stage' => ['x']])->assertOk()->assertSee('Reg One');
    }

    public function test_search_finds_by_name_mobile_or_member_code(): void
    {
        $a = $this->person('registered', 'Anita Shah', null, '9876500001');
        $this->person('registered', 'Bharat Mehta', null, '9123400002');

        $this->page(['search' => 'anita'])->assertSee('Anita Shah')->assertDontSee('Bharat Mehta');
        $this->page(['search' => '91234'])->assertSee('Bharat Mehta')->assertDontSee('Anita Shah');
        $this->page(['search' => $a->member_code])->assertSee('Anita Shah')->assertDontSee('Bharat Mehta');
    }

    public function test_search_treats_percent_and_underscore_as_plain_characters(): void
    {
        $this->person('registered', 'Anita Shah');
        $this->person('registered', 'Bharat Mehta');

        $this->page(['search' => '%'])->assertOk()->assertDontSee('Anita Shah')->assertDontSee('Bharat Mehta');
        $this->page(['search' => '_'])->assertOk()->assertDontSee('Anita Shah');
    }

    public function test_search_and_stage_work_together_and_the_page_links_keep_them(): void
    {
        foreach (range(1, 30) as $i) {
            $this->person('order_placed', 'Shah Order '.$i);
        }
        $this->person('registered', 'Shah Registered');

        $res = $this->page(['stage' => 'order_placed', 'search' => 'Shah'])->assertOk()->assertDontSee('Shah Registered');
        $this->assertSame(30, $res->viewData('people')->total());
        $res->assertSee('stage=order_placed', false)->assertSee('search=Shah', false)->assertSee('page=2', false);
    }

    public function test_the_region_filter_narrows_the_list(): void
    {
        $gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
        $this->person('registered', 'Main Person');
        $this->person('registered', 'Gujarat Person', $gj);

        $this->page(['region' => $gj->id])->assertSee('Gujarat Person')->assertDontSee('Main Person');
        $this->page(['region' => 'abc'])->assertOk()->assertSee('Main Person')->assertSee('Gujarat Person');
    }

    public function test_longest_waiting_come_first_by_default_and_newest_first_on_request(): void
    {
        $this->person('registered', 'Waiting Old', null, null, now()->subDays(20)->toDateTimeString());
        $this->person('registered', 'Waiting New', null, null, now()->subDay()->toDateTimeString());

        $this->assertSame(['Waiting Old', 'Waiting New'], $this->page()->viewData('people')->pluck('name')->all());
        $this->assertSame(['Waiting New', 'Waiting Old'], $this->page(['sort' => 'newest'])->viewData('people')->pluck('name')->all());
    }

    public function test_it_does_not_run_a_query_per_person(): void
    {
        $queriesFor = function (): int {
            $this->page(); // warm up caches (settings etc.)
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->page()->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $add = fn (int $from, int $to) => collect(range($from, $to))->each(
            fn ($i) => $this->person($i % 3 === 0 ? 'payment_submitted' : ($i % 3 === 1 ? 'order_placed' : 'registered'))
        );

        $add(1, 5);
        $few = $queriesFor();
        $add(6, 25);
        $many = $queriesFor();

        $this->assertSame($few, $many, "5 people ran {$few} queries but 25 ran {$many}: something is queried once per person");
    }

    public function test_an_empty_filter_says_so_and_offers_a_way_back(): void
    {
        $this->person('registered', 'Anita Shah');

        $this->page(['search' => 'zzz'])->assertOk()->assertSee('No one matches')->assertSee(route('admin.activation.index'), false);
    }

    public function test_active_people_stay_out_of_every_filter_and_count(): void
    {
        $m = $this->person('payment_submitted', 'Now Active');
        $m->update(['status' => 'active']);

        $res = $this->page(['stage' => 'all'])->assertDontSee('Now Active');
        $this->assertSame(0, $res->viewData('counts')['total']);
    }
}
