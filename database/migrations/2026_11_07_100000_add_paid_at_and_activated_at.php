<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payments.paid_at: the date and time the member really paid, entered by the admin when approving (online payments
     * take the gateway time). members.activated_at: the paid_at of their first approved payment. The sponsor-income day
     * rule compares these two dates. Existing rows are filled in from what is already recorded.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('verified_at');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('status');
        });

        DB::table('payments')->where('status', 'verified')->whereNull('paid_at')->update(['paid_at' => DB::raw('COALESCE(verified_at, created_at)')]);

        $first = DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'verified')->groupBy('orders.member_id')
            ->selectRaw('orders.member_id as member_id, MIN(payments.paid_at) as first_paid')->get();

        foreach ($first as $row) {
            DB::table('members')->where('id', $row->member_id)->whereNull('activated_at')->update(['activated_at' => $row->first_paid]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('paid_at'));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('activated_at'));
    }
};
