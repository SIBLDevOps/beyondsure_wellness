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
        Schema::table('products', function (Blueprint $table) {
            // Null = use the Settings -> 0. Tax assumption default rate. Set here only when this
            // specific product is taxed at a different GST slab than the company-wide default.
            $table->decimal('gst_pct', 5, 2)->nullable()->after('sell_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('gst_pct');
        });
    }
};
