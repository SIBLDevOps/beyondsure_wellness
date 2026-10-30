<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The plan a person said they want to buy when they registered. Nothing is lost if the plan is later removed.
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('interested_plan_id')->nullable()->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interested_plan_id');
        });
    }
};
