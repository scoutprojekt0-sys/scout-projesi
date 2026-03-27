<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table) {
            $table->json('note_history')->nullable()->after('note');
            $table->json('performance_history')->nullable()->after('rating');
            $table->json('timeline_events')->nullable()->after('performance_history');
        });
    }

    public function down(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table) {
            $table->dropColumn(['note_history', 'performance_history', 'timeline_events']);
        });
    }
};
