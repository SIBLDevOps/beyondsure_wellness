<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax invoices need a unique, consecutive number within each financial year (April to March). The number and date
 * are written onto the order the moment its payment is approved; invoice_sequences hands out the next number.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'invoice_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('invoice_number', 40)->nullable()->unique();
                $table->date('invoice_date')->nullable();
            });
        }

        if (! Schema::hasTable('invoice_sequences')) {
            Schema::create('invoice_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 7)->unique(); // e.g. 2026-27
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');

        if (Schema::hasColumn('orders', 'invoice_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['invoice_number']);
                $table->dropColumn(['invoice_number', 'invoice_date']);
            });
        }
    }
};
