<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\CompensationEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SevenLevelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Delete all existing transaction and network records
        Schema::disableForeignKeyConstraints();

        DB::table('ledger_entries')->truncate();
        DB::table('payments')->truncate();
        DB::table('orders')->truncate();
        if (Schema::hasTable('dispatch_batches')) {
            DB::table('dispatch_batches')->truncate();
        }
        DB::table('withdrawals')->truncate();
        DB::table('member_leg_totals')->truncate();
        DB::table('member_weekly_totals')->truncate();
        DB::table('members')->truncate();
        DB::table('cycles')->truncate();

        Schema::enableForeignKeyConstraints();

        // 2. Fetch available plans
        $plans = Plan::where('is_active', true)->get()->keyBy('slug');
        $essential = $plans['essential'] ?? Plan::first();
        $plus = $plans['plus'] ?? $essential;
        $prime = $plans['prime'] ?? $plus;
        $elite = $plans['elite'] ?? $prime;

        $password = Hash::make('password');

        // Helper to create member
        $createMember = function (
            string $code,
            string $name,
            string $phone,
            ?Member $sponsor = null,
            ?Member $placement = null,
            ?string $position = null
        ) use ($password): Member {
            $path = $placement ? (($placement->path ?? '').$placement->id.'.') : null;

            return Member::create([
                'member_code' => $code,
                'name' => $name,
                'phone' => $phone,
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                'password' => $password,
                'sponsor_id' => $sponsor?->id,
                'placement_id' => $placement?->id,
                'position' => $position,
                'path' => $path,
                'status' => 'active',
                'rank' => 'Partner',
                'bank_name' => 'HDFC Bank',
                'bank_account_name' => $name,
                'bank_account_number' => '50100'.rand(1000000, 9999999),
                'bank_ifsc' => 'HDFC0001234',
                'upi_id' => strtolower(str_replace(' ', '', $name)).'@upi',
            ]);
        };

        // ----------------------------------------------------
        // BUILD 7-LEVEL TREE (Root down to Level 7)
        // ----------------------------------------------------

        // Level 0: ROOT
        $root = $createMember('BS100001', 'Rajesh Sharma', '9820011000');

        // Level 1 (2 members)
        $l1_left = $createMember('BS100002', 'Amit Patel', '9820011001', $root, $root, 'left');
        $l1_right = $createMember('BS100003', 'Priya Verma', '9820011002', $root, $root, 'right');

        // Level 2 (4 members)
        $l2_a = $createMember('BS100004', 'Vikram Singh', '9820011003', $l1_left, $l1_left, 'left');
        $l2_b = $createMember('BS100005', 'Neha Gupta', '9820011004', $l1_left, $l1_left, 'right');
        $l2_c = $createMember('BS100006', 'Suresh Nair', '9820011005', $l1_right, $l1_right, 'left');
        $l2_d = $createMember('BS100007', 'Anjali Rao', '9820011006', $l1_right, $l1_right, 'right');

        // Level 3 (4 members under left & right branches)
        $l3_a = $createMember('BS100008', 'Manoj Joshi', '9820011007', $l2_a, $l2_a, 'left');
        $l3_b = $createMember('BS100009', 'Pooja Mehta', '9820011008', $l2_a, $l2_a, 'right');
        $l3_c = $createMember('BS100010', 'Karan Malhotra', '9820011009', $l2_c, $l2_c, 'left');
        $l3_d = $createMember('BS100011', 'Ritu Saxena', '9820011010', $l2_c, $l2_c, 'right');

        // Level 4 (4 members)
        $l4_a = $createMember('BS100012', 'Deepak Chawla', '9820011011', $l3_a, $l3_a, 'left');
        $l4_b = $createMember('BS100013', 'Sunita Iyer', '9820011012', $l3_a, $l3_a, 'right');
        $l4_c = $createMember('BS100014', 'Rohit Desai', '9820011013', $l3_c, $l3_c, 'left');
        $l4_d = $createMember('BS100015', 'Meera Kulkarni', '9820011014', $l3_c, $l3_c, 'right');

        // Level 5 (4 members)
        $l5_a = $createMember('BS100016', 'Arun Pillai', '9820011015', $l4_a, $l4_a, 'left');
        $l5_b = $createMember('BS100017', 'Kavita Reddy', '9820011016', $l4_a, $l4_a, 'right');
        $l5_c = $createMember('BS100018', 'Gaurav Bhatia', '9820011017', $l4_c, $l4_c, 'left');
        $l5_d = $createMember('BS100019', 'Bhavna Shah', '9820011018', $l4_c, $l4_c, 'right');

        // Level 6 (4 members)
        $l6_a = $createMember('BS100020', 'Sanjay Sen', '9820011019', $l5_a, $l5_a, 'left');
        $l6_b = $createMember('BS100021', 'Rashmi Jain', '9820011020', $l5_a, $l5_a, 'right');
        $l6_c = $createMember('BS100022', 'Nikhil Chopra', '9820011021', $l5_c, $l5_c, 'left');
        $l6_d = $createMember('BS100023', 'Sneha Mathur', '9820011022', $l5_c, $l5_c, 'right');

        // Level 7 (4 members)
        $l7_a = $createMember('BS100024', 'Aditya Nambiar', '9820011023', $l6_a, $l6_a, 'left');
        $l7_b = $createMember('BS100025', 'Divya Hegde', '9820011024', $l6_a, $l6_a, 'right');
        $l7_c = $createMember('BS100026', 'Harish Menon', '9820011025', $l6_c, $l6_c, 'left');
        $l7_d = $createMember('BS100027', 'Preeti Kaul', '9820011026', $l6_c, $l6_c, 'right');

        $allMembers = [
            $root,
            $l1_left, $l1_right,
            $l2_a, $l2_b, $l2_c, $l2_d,
            $l3_a, $l3_b, $l3_c, $l3_d,
            $l4_a, $l4_b, $l4_c, $l4_d,
            $l5_a, $l5_b, $l5_c, $l5_d,
            $l6_a, $l6_b, $l6_c, $l6_d,
            $l7_a, $l7_b, $l7_c, $l7_d,
        ];

        // 3. Create Orders for each member to generate real BV & Level commissions
        $planCycle = [$essential, $plus, $prime, $elite];
        $orderSeq = 1000;

        foreach ($allMembers as $idx => $m) {
            $plan = $planCycle[$idx % count($planCycle)];
            $gstAmount = round($plan->price * 0.12, 2);
            $totalAmount = $plan->price + $gstAmount;

            $order = Order::create([
                'order_code' => 'ORD-'.(++$orderSeq),
                'member_id' => $m->id,
                'plan_id' => $plan->id,
                'amount' => $totalAmount,
                'taxable_amount' => $plan->price,
                'gst_amount' => $gstAmount,
                'bv' => $plan->bv,
                'status' => 'paid',
                'created_at' => now()->startOfMonth()->addHours(6),
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => 'upi',
                'reference' => 'UPI'.rand(100000000000, 999999999999),
                'status' => 'verified',
                'verified_at' => now()->startOfMonth()->addHours(6),
            ]);
        }

        // 4. Run Compensation Cycle to distribute all 7 levels of commission
        $cycle = Cycle::create([
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfDay(),
            'status' => 'draft',
        ]);

        $engine = new CompensationEngine;
        $engine->run($cycle);
    }
}
