<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements the company posts to its members (shown in the member area and optionally emailed), and a flag so a
 * member can stop the optional emails (announcements and new-plan news). Emails about their own orders and payments
 * are never switched off by it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title', 150);
                $table->text('body');
                $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete(); // null = every region
                $table->string('audience', 10)->default('all');                              // all | active
                $table->boolean('send_email')->default(false);
                $table->unsignedInteger('emailed_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('members', 'email_opt_out')) {
            Schema::table('members', function (Blueprint $table) {
                $table->boolean('email_opt_out')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');

        if (Schema::hasColumn('members', 'email_opt_out')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('email_opt_out');
            });
        }
    }
};
