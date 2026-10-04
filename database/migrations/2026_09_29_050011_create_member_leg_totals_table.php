<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lifetime cumulative BV per leg, and how much has already been matched & paid —
        // carry-forward on the unmatched side never expires (Guide p.3).
        Schema::create('member_leg_totals', function (Blueprint $table) {
            $table->foreignId('member_id')->primary()->constrained()->cascadeOnDelete();
            $table->decimal('left_bv', 14, 2)->default(0);
            $table->decimal('right_bv', 14, 2)->default(0);
            $table->decimal('matched_bv', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_leg_totals');
    }
};
