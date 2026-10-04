<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks matching payouts by ISO week, independent of cycle length, so the weekly
        // cap (Phase 2 §3.4, option 2 — the "correct" approach per the spec) can be enforced
        // regardless of how long the admin's chosen cycle period is.
        Schema::create('member_weekly_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->decimal('matched_total', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['member_id', 'iso_year', 'iso_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_weekly_totals');
    }
};
