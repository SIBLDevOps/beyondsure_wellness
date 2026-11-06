<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matching income a member would have earned in a cycle but could not receive because they had not bought a plan.
     * It is never paid and never counts in the wallet; it is only shown to the member, with a message to activate.
     */
    public function up(): void
    {
        Schema::create('locked_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('matching');
            $table->unsignedTinyInteger('level')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locked_incomes');
    }
};
