<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\Setting;
use Database\Seeders\StarterPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterPlansSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_active_plans_in_the_main_region(): void
    {
        $this->seed(StarterPlansSeeder::class);

        $main = Region::where('code', 'MAIN')->firstOrFail();
        $plans = Plan::where('region_id', $main->id)->where('is_active', true)->orderBy('price')->get();

        $this->assertCount(3, $plans);
        $this->assertSame([2500.0, 5000.0, 10000.0], $plans->map(fn ($p) => (float) $p->price)->all());
        $plans->each(fn (Plan $p) => $this->assertGreaterThan(0, $p->products()->count(), $p->name.' has products'));
    }

    public function test_running_it_again_does_not_duplicate_anything(): void
    {
        $this->seed(StarterPlansSeeder::class);
        $this->seed(StarterPlansSeeder::class);

        $this->assertSame(3, Plan::count());
        $this->assertSame(3, Product::count());
    }

    public function test_every_plan_respects_the_cost_cap_and_every_product_has_its_own_gst(): void
    {
        $this->seed(StarterPlansSeeder::class);
        $costPct = (float) Setting::get('split_cost_pct', 40);

        Plan::with('products')->get()->each(function (Plan $p) use ($costPct) {
            $this->assertLessThanOrEqual($p->price * $costPct / 100, $p->costForQty(), $p->name.' cost is within the cost share');
            $this->assertEqualsWithDelta((float) $p->price * 0.4, (float) $p->bv, 0.01, $p->name.' BV is the pool share');
        });
        Product::all()->each(fn (Product $pr) => $this->assertNotNull($pr->gst_pct));
    }

    public function test_the_register_page_then_asks_which_plan(): void
    {
        $this->seed(StarterPlansSeeder::class);

        $this->get(route('register'))->assertOk()
            ->assertSee('Which plan do you want to buy?')->assertSee('Wellness Starter')->assertSee('Premium Wellness');
    }
}
