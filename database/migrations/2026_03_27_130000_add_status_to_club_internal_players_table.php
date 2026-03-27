<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_internal_players', 'status')) {
                $table->string('status', 40)->default('active')->after('group_key');
                $table->index(['club_user_id', 'group_key', 'status'], 'club_internal_players_club_group_status_idx');
            }
        });

        DB::table('club_internal_players')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            if (Schema::hasColumn('club_internal_players', 'status')) {
                $table->dropIndex('club_internal_players_club_group_status_idx');
                $table->dropColumn('status');
            }
        });
    }
};
