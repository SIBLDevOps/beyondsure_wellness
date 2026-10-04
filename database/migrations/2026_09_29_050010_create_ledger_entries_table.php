<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            // The member whose purchase/matched-volume generated this income (null for withdrawal debits).
            $table->foreignId('source_member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('withdrawal_id')->nullable();

            $table->enum('type', ['self', 'sponsor', 'matching', 'rank', 'withdrawal']);
            $table->decimal('amount', 12, 2); // positive = credit, negative = debit (withdrawal)
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
