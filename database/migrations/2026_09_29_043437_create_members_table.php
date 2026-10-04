<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->string('password')->nullable();

            // Sponsor = who personally introduced this member (for sponsor income, regardless of tree placement)
            $table->foreignId('sponsor_id')->nullable()->constrained('members')->nullOnDelete();

            // Placement = binary tree parent (where this member sits in the left/right tree)
            $table->foreignId('placement_id')->nullable()->constrained('members')->nullOnDelete();
            $table->enum('position', ['left', 'right'])->nullable();

            // Materialized path for fast subtree scoping, e.g. "1.4.9." — see NetworkService::subtreeOf()
            $table->string('path')->nullable()->index();

            $table->string('rank')->default('none');
            $table->enum('status', ['active', 'inactive'])->default('inactive');

            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
