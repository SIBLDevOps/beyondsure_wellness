<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_batches', function (Blueprint $table) {
            $table->id();
            $table->date('scheduled_for');
            $table->enum('status', ['draft', 'released'])->default('draft');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->unsignedInteger('withdrawal_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatch_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('withdrawal_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dispatch_batch_id', 'withdrawal_id']);
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreign('withdrawal_id')->references('id')->on('withdrawals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['withdrawal_id']);
        });
        Schema::dropIfExists('dispatch_batch_items');
        Schema::dropIfExists('dispatch_batches');
    }
};
