<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A region is a self-contained market: its own binary tree, its own plan catalogue and its own
 * invite links. Everything that already exists is moved into one starting region so nothing is
 * orphaned; the app treats region_id as required from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions');
        });

        $id = DB::table('regions')->insertGetId([
            'name' => 'Main Region', 'code' => 'MAIN', 'description' => 'Starting region created for existing members and plans.',
            'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('members')->update(['region_id' => $id]);
        DB::table('plans')->update(['region_id' => $id]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
        });
        Schema::dropIfExists('regions');
    }
};
