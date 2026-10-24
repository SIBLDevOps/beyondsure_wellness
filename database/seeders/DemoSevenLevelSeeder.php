<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Region;
use App\Support\Gst;
use Illuminate\Database\Seeder;

/**
 * Adds a separate demo region with a complete team: 8 tree levels, so that all 7 payout levels are paid (a full binary tree: 1 + 2 + 4 + ... + 64
 * = 255 members for 8 levels), each with a paid order. Nothing existing is changed or removed, and it can be run again
 * safely (it does nothing if the demo region is already there). Orders are left paid but not settled: approve
 * a cycle from the admin Cycles page to post the income and fill every report.
 *
 *   php artisan db:seed --class=DemoSevenLevelSeeder
 */
class DemoSevenLevelSeeder extends Seeder
{
    private const LEVELS = 8; // payout level 7 is paid to the 7th ancestor of a matching member, so the tree must be 8 deep to fill it

    public function run(): void
    {
        if (Region::where('code', 'DEMO7')->exists()) {
            $this->command?->warn('Demo region DEMO7 already exists. Nothing to do.');

            return;
        }

        $region = Region::create([
            'name' => 'Demo 7-Level', 'code' => 'DEMO7', 'is_active' => true,
            'sort_order' => (int) Region::max('sort_order') + 1,
            'description' => 'Demo data: a full team on all 7 levels. Safe to ignore or close.',
        ]);

        // Plans for this region, built from the products of an existing plan so costs and GST are realistic.
        $products = Plan::with('products')->has('products')->first()?->products ?? collect();
        $plans = collect([['Demo Essential', 2000, 800], ['Demo Plus', 3500, 1400], ['Demo Prime', 6000, 2400]])->map(function ($p) use ($region, $products) {
            $plan = Plan::create(['region_id' => $region->id, 'name' => $p[0], 'slug' => str($p[0])->slug(), 'price' => $p[1], 'bv' => $p[2], 'is_active' => true]);
            $plan->products()->sync($products->mapWithKeys(fn ($pr) => [$pr->id => ['qty' => $pr->pivot->qty]])->all());

            return $plan->load('products');
        });

        $seq = 0;
        $make = function (?Member $parent, ?string $position) use ($region, &$seq): Member {
            $seq++;

            return Member::create([
                'region_id' => $region->id,
                'member_code' => sprintf('DM%04d', $seq),
                'name' => 'Demo Member '.$seq,
                'phone' => (string) (9500000000 + $seq),
                'sponsor_id' => $parent?->id,
                'placement_id' => $parent?->id,
                'position' => $position,
                'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
                'status' => 'active',
                'rank' => 'none',
                'bank_name' => 'HDFC Bank', 'bank_account_name' => 'Demo Member '.$seq,
                'bank_account_number' => (string) (50100000000 + $seq), 'bank_ifsc' => 'HDFC0001234',
            ]);
        };

        // Level 1 is the root; build each next level under the one before it.
        $level = [$make(null, null)];
        $all = $level;
        for ($l = 2; $l <= self::LEVELS; $l++) {
            $next = [];
            foreach ($level as $parent) {
                $next[] = $make($parent, 'left');
                $next[] = $make($parent, 'right');
            }
            $all = array_merge($all, $next);
            $level = $next;
        }

        foreach ($all as $i => $member) {
            $plan = $plans[$i % $plans->count()];
            $split = Gst::splitForPlan($plan, (float) $plan->price);

            $order = Order::create([
                'order_code' => 'ORD-D7'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'member_id' => $member->id, 'plan_id' => $plan->id,
                'amount' => $split['total'], 'taxable_amount' => $split['taxable'], 'gst_amount' => $split['gst'],
                'bv' => $plan->bv, 'status' => 'paid', 'is_repurchase' => false,
            ]);

            Payment::create(['order_id' => $order->id, 'method' => 'upi', 'reference' => 'DEMO'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT), 'status' => 'verified', 'verified_at' => now()]);
        }

        $this->command?->info(count($all).' demo members created across '.self::LEVELS.' levels in region "Demo 7-Level". Approve a cycle to post their income.');
    }
}
