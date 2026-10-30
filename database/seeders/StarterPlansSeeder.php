<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * A starter catalogue so a new install has something to sell: three products and three plans in the Main region.
 * Safe to run again (nothing is duplicated, nothing you edited is overwritten), and safe on production.
 *
 *     php artisan db:seed --class=StarterPlansSeeder
 *
 * The names, prices and descriptions are examples. Change them in Admin -> Products and Admin -> Plans.
 * A plan's price is the taxable base; GST is added on top at checkout using each product's own rate, and the
 * product cost inside a plan stays within the cost share (40% by default).
 */
class StarterPlansSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::firstOrCreate(['slug' => 'wellness'], ['name' => 'Wellness', 'is_free' => false]);

        $products = [];
        foreach ([
            ['IMM-001', 'Immunity Booster', 'Daily immunity support with herbal extracts. One month pack.', 500, 1200],
            ['JNT-001', 'Joint Care', 'Supports joint comfort and mobility. One month pack.', 700, 1800],
            ['DTX-001', 'Daily Detox', 'Gentle daily detox blend for better digestion. One month pack.', 400, 1000],
        ] as [$sku, $name, $description, $cost, $sell]) {
            $products[$sku] = Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $category->id, 'name' => $name, 'description' => $description,
                'cost_price' => $cost, 'sell_price' => $sell, 'gst_pct' => 12, 'is_active' => true,
            ]);
        }

        $regionId = Region::fallbackId();

        foreach ([
            ['Wellness Starter', 'Everything you need to begin: immunity and detox for one month.', 2500, ['IMM-001' => 1, 'DTX-001' => 1]],
            ['Family Wellness', 'A fuller month for the whole family: double immunity plus joint care.', 5000, ['IMM-001' => 2, 'JNT-001' => 1]],
            ['Premium Wellness', 'The complete programme: immunity, joint care and detox, in larger quantities.', 10000, ['IMM-001' => 3, 'JNT-001' => 2, 'DTX-001' => 2]],
        ] as [$name, $description, $price, $bundle]) {
            $plan = Plan::firstOrCreate(
                ['slug' => str($name)->slug()->toString(), 'region_id' => $regionId],
                ['name' => $name, 'description' => $description, 'price' => $price, 'bv' => $price * 0.4, 'is_active' => true],
            );

            if ($plan->wasRecentlyCreated) {
                $plan->products()->sync(collect($bundle)->mapWithKeys(fn ($qty, $sku) => [$products[$sku]->id => ['qty' => $qty]])->all());
            }
        }

        $this->command?->info('Starter products and plans are ready. Edit them under Admin -> Products and Plans.');
    }
}
