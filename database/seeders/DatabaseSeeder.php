<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@beyondsure.test'],
            ['name' => 'BeyondSure Admin', 'password' => bcrypt('password'), 'role' => 'admin']
        );

        foreach ([
            'split_cost_pct' => 40,
            'split_pool_pct' => 40,
            'split_company_pct' => 20,
            'self_pct' => 10,
            'sponsor_pct' => 20,
            'matching_pct' => 10,
            'cascade_depth' => 7,
            'rank_pool_pct' => 3,
            'match_cap_per_cycle' => 600000,
            'match_cap_per_week' => 150000,
        ] as $key => $value) {
            Setting::set($key, $value);
        }

        foreach ([
            ['name' => 'Silver', 'sort_order' => 1, 'matched_bv_threshold' => 20000, 'share' => 1],
            ['name' => 'Gold', 'sort_order' => 2, 'matched_bv_threshold' => 100000, 'share' => 2],
            ['name' => 'Platinum', 'sort_order' => 3, 'matched_bv_threshold' => 500000, 'share' => 3],
            ['name' => 'Diamond', 'sort_order' => 4, 'matched_bv_threshold' => 2000000, 'share' => 5],
        ] as $rank) {
            Rank::updateOrCreate(['name' => $rank['name']], $rank + ['min_active_directs' => 2]);
        }

        $syrup = Category::updateOrCreate(['slug' => 'wellness-syrup'], ['name' => 'Wellness Syrup', 'is_free' => false]);
        $rollon = Category::updateOrCreate(['slug' => 'roll-on'], ['name' => 'Roll-on Balm', 'is_free' => false]);
        $tea = Category::updateOrCreate(['slug' => 'herbal-tea'], ['name' => 'Herbal Tea', 'is_free' => false]);
        $complimentary = Category::updateOrCreate(['slug' => 'complimentary'], ['name' => 'Complimentary Services', 'is_free' => true]);

        $syrupProduct = Product::updateOrCreate(['sku' => 'SYR-001'], [
            'category_id' => $syrup->id, 'name' => 'Immunity Wellness Syrup 200ml',
            'cost_price' => 150, 'sell_price' => 300, 'is_active' => true,
        ]);
        $rollonProduct = Product::updateOrCreate(['sku' => 'ROL-001'], [
            'category_id' => $rollon->id, 'name' => 'Pain Relief Roll-on',
            'cost_price' => 80, 'sell_price' => 200, 'is_active' => true,
        ]);
        $teaProduct = Product::updateOrCreate(['sku' => 'TEA-001'], [
            'category_id' => $tea->id, 'name' => 'Herbal Detox Tea Box',
            'cost_price' => 50, 'sell_price' => 150, 'is_active' => true,
        ]);

        Product::updateOrCreate(['sku' => 'BEN-TELE'], [
            'category_id' => $complimentary->id, 'name' => 'Doctor Teleconsultation',
            'cost_price' => 0, 'sell_price' => 0, 'is_active' => true,
            'benefit_group' => 'Health & Digital Care', 'usage_limit' => 2, 'usage_period' => 'year',
        ]);
        Product::updateOrCreate(['sku' => 'BEN-ROAD'], [
            'category_id' => $complimentary->id, 'name' => 'Roadside Assistance',
            'cost_price' => 0, 'sell_price' => 0, 'is_active' => true,
            'benefit_group' => 'Roadside & Emergency Assistance', 'usage_limit' => 2, 'usage_period' => 'year',
        ]);
        Product::updateOrCreate(['sku' => 'BEN-DISC'], [
            'category_id' => $complimentary->id, 'name' => 'Pharmacy Discount Card',
            'cost_price' => 0, 'sell_price' => 0, 'is_active' => true,
            'benefit_group' => 'Everyday Discounts',
        ]);
        Product::updateOrCreate(['sku' => 'BEN-PA'], [
            'category_id' => $complimentary->id, 'name' => 'Personal Accident Cover',
            'cost_price' => 0, 'sell_price' => 0, 'is_active' => true,
            'benefit_group' => 'Protection Cover',
        ]);

        $bundles = [
            ['name' => 'Essential', 'slug' => 'essential', 'price' => 2000, 'bv' => 800, 'qty' => [4, 1, 1]],
            ['name' => 'Plus', 'slug' => 'plus', 'price' => 3500, 'bv' => 1400, 'qty' => [6, 2, 2]],
            ['name' => 'Prime', 'slug' => 'prime', 'price' => 6000, 'bv' => 2400, 'qty' => [8, 3, 3]],
            ['name' => 'Elite', 'slug' => 'elite', 'price' => 10000, 'bv' => 4000, 'qty' => [8, 6, 4]],
        ];

        foreach ($bundles as $b) {
            $plan = Plan::updateOrCreate(['slug' => $b['slug']], [
                'name' => $b['name'],
                'description' => "{$b['name']} wellness bundle — {$b['qty'][0]} syrup / {$b['qty'][1]} roll-on / {$b['qty'][2]} tea, plus complimentary benefits.",
                'price' => $b['price'],
                'bv' => $b['bv'],
                'is_active' => true,
            ]);

            $plan->products()->sync([
                $syrupProduct->id => ['qty' => $b['qty'][0]],
                $rollonProduct->id => ['qty' => $b['qty'][1]],
                $teaProduct->id => ['qty' => $b['qty'][2]],
            ]);
        }
    }
}
