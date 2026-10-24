<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = this person sees every region; a list of ids = only those regions (sub-admins only).
        Schema::table('users', function (Blueprint $table) {
            $table->json('region_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region_ids');
        });
    }
};
