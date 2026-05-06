<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scout_player_reports', function (Blueprint $table) {
            $table->json('shared_roles')->nullable()->after('risks');
        });
    }

    public function down(): void
    {
        Schema::table('scout_player_reports', function (Blueprint $table) {
            $table->dropColumn('shared_roles');
        });
    }
};
