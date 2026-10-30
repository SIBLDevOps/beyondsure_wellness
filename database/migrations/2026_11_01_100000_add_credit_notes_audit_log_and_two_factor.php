<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes (a numbered document that reverses a paid order's invoice), the admin audit log (who did what, never
 * edited), and the columns for two-factor sign-in of staff.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('number', 40)->unique();
                $table->date('issued_on');
                $table->string('reason', 255);
                $table->decimal('taxable_amount', 12, 2);
                $table->decimal('gst_amount', 12, 2);
                $table->decimal('amount', 12, 2);
                $table->string('refund_method', 20)->nullable();      // upi | bank_transfer | cash | original
                $table->string('refund_reference', 255)->nullable();  // the bank / UPI reference of the money sent back
                $table->date('refunded_on')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('credit_note_sequences')) {
            Schema::create('credit_note_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('financial_year', 7)->unique();
                $table->unsignedInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();   // no foreign key: the log must outlive the account
                $table->string('user_name', 120)->nullable();
                $table->string('action', 150)->index();                         // route name or event, e.g. admin.payments.verify
                $table->string('method', 10)->nullable();
                $table->string('path', 255)->nullable();
                $table->string('subject', 120)->nullable();                     // e.g. "payment #12"
                $table->text('details')->nullable();                            // field names that were sent, never their values
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('credit_note_sequences');
        Schema::dropIfExists('credit_notes');

        if (Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
            });
        }
    }
};
