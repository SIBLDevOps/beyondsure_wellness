<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // v2 model §2B: classification only — Self/Sponsor/Matching pay identical rates for
            // New and Repurchase by design. This exists purely so the repurchase ratio can be
            // reported, never to change a payout. Set once at order creation; never recomputed.
            $table->boolean('is_repurchase')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_repurchase');
        });
    }
};
