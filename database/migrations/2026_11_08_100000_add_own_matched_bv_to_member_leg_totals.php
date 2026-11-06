<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * own_matched_bv: how much of a member's matched volume has already been paid to THEM as level 1 (their own match).
     * It normally moves together with matched_bv. It stays behind while a member placed directly under them is not
     * active, so the amount waits and is paid after that member activates. Existing rows start level with matched_bv:
     * nothing already settled is paid again.
     */
    public function up(): void
    {
        Schema::table('member_leg_totals', function (Blueprint $table) {
            $table->decimal('own_matched_bv', 14, 2)->default(0)->after('matched_bv');
        });

        DB::table('member_leg_totals')->update(['own_matched_bv' => DB::raw('matched_bv')]);
    }

    public function down(): void
    {
        Schema::table('member_leg_totals', fn (Blueprint $table) => $table->dropColumn('own_matched_bv'));
    }
};