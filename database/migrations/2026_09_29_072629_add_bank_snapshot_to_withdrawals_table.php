<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // Snapshot of the member's bank details at request time — kept even if the
            // member later edits their profile, so admin always pays out what was requested.
            $table->string('bank_account_name')->nullable()->after('amount');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc')->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc');
            $table->string('upi_id')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['bank_account_name', 'bank_account_number', 'bank_ifsc', 'bank_name', 'upi_id']);
        });
    }
};
