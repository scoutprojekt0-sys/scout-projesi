<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_internal_players', 'photo_url')) {
                $table->string('photo_url', 2048)->nullable()->after('shirt_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            if (Schema::hasColumn('club_internal_players', 'photo_url')) {
                $table->dropColumn('photo_url');
            }
        });
    }
};
