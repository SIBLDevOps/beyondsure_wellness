<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log becomes a full track record: who (and their role), what kind of event, whether it worked, from which
 * browser, and which request it belongs to. And the database itself now refuses to change or delete a line, so not even
 * someone with direct database access using a plain UPDATE or DELETE can rewrite history quietly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'user_role')) {
                $table->string('user_role', 20)->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('audit_logs', 'outcome')) {
                $table->string('outcome', 10)->default('success')->index();   // success | failed | denied
            }
            if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 255)->nullable();
            }
            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 36)->nullable()->index();        // ties a request to the changes it caused
            }
        });

        $this->protect();
    }

    /** Triggers that stop UPDATE and DELETE. If the database user may not create triggers the log is still protected in code. */
    private function protect(): void
    {
        $driver = DB::getDriverName();

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                foreach (['update' => 'changed', 'delete' => 'deleted'] as $event => $word) {
                    DB::unprepared("DROP TRIGGER IF EXISTS audit_logs_no_{$event}");
                    DB::unprepared("CREATE TRIGGER audit_logs_no_{$event} BEFORE ".strtoupper($event)." ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log entries cannot be {$word}.'");
                }
            } elseif ($driver === 'sqlite') {
                foreach (['update' => 'changed', 'delete' => 'deleted'] as $event => $word) {
                    DB::unprepared("CREATE TRIGGER IF NOT EXISTS audit_logs_no_{$event} BEFORE ".strtoupper($event)." ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit log entries cannot be {$word}.'); END;");
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        foreach (['update', 'delete'] as $event) {
            try {
                DB::unprepared("DROP TRIGGER IF EXISTS audit_logs_no_{$event}");
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                foreach (['request_id', 'user_agent', 'outcome', 'category', 'user_role'] as $column) {
                    if (Schema::hasColumn('audit_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
