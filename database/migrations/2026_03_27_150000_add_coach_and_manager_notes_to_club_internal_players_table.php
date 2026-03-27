<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table) {
            $table->text('coach_note')->nullable()->after('bio');
            $table->text('manager_note')->nullable()->after('coach_note');
        });
    }

    public function down(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table) {
            $table->dropColumn(['coach_note', 'manager_note']);
        });
    }
};
