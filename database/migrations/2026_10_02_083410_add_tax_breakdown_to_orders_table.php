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
            // Snapshotted at order creation, like the withdrawal bank-detail snapshot — a later
            // change to a product's GST rate must never retroactively alter an already-placed
            // order's tax breakdown. amount = taxable_amount + gst_amount (GST is added ON TOP
            // of the plan's base price, per the v2 model's "Package Tiers, MRP excl. GST").
            $table->decimal('taxable_amount', 12, 2)->nullable()->after('amount');
            $table->decimal('gst_amount', 12, 2)->nullable()->after('taxable_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['taxable_amount', 'gst_amount']);
        });
    }
};
