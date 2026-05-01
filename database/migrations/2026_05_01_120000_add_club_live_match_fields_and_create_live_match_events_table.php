<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            if (!Schema::hasColumn('live_matches', 'visibility')) {
                $table->string('visibility', 30)->default('public')->after('is_finished');
                $table->index(['visibility', 'is_live', 'is_finished'], 'live_matches_visibility_status_idx');
            }

            if (!Schema::hasColumn('live_matches', 'club_user_id')) {
                $table->foreignId('club_user_id')->nullable()->after('visibility')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('live_matches', 'group_key')) {
                $table->string('group_key', 40)->nullable()->after('club_user_id');
            }

            if (!Schema::hasColumn('live_matches', 'periods')) {
                $table->unsignedTinyInteger('periods')->nullable()->after('group_key');
            }

            if (!Schema::hasColumn('live_matches', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('periods');
            }

            if (!Schema::hasColumn('live_matches', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }

            $table->index(['club_user_id', 'group_key'], 'live_matches_club_group_idx');
        });

        DB::table('live_matches')
            ->whereNull('visibility')
            ->update(['visibility' => 'public']);

        if (!Schema::hasTable('live_match_events')) {
            Schema::create('live_match_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('live_match_id')->constrained('live_matches')->cascadeOnDelete();
                $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('club_internal_player_id')->constrained('club_internal_players')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('group_key', 40)->nullable();
                $table->string('event_type', 80);
                $table->unsignedTinyInteger('period');
                $table->unsignedTinyInteger('minute');
                $table->unsignedTinyInteger('second');
                $table->decimal('x', 6, 4)->nullable();
                $table->decimal('y', 6, 4)->nullable();
                $table->timestamps();

                $table->index(['live_match_id', 'created_at'], 'live_match_events_match_created_idx');
                $table->index(['club_internal_player_id', 'created_at'], 'live_match_events_player_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_match_events');

        Schema::table('live_matches', function (Blueprint $table): void {
            if (Schema::hasColumn('live_matches', 'club_user_id') || Schema::hasColumn('live_matches', 'group_key')) {
                $table->dropIndex('live_matches_club_group_idx');
            }

            if (Schema::hasColumn('live_matches', 'finished_at')) {
                $table->dropColumn('finished_at');
            }

            if (Schema::hasColumn('live_matches', 'started_at')) {
                $table->dropColumn('started_at');
            }

            if (Schema::hasColumn('live_matches', 'periods')) {
                $table->dropColumn('periods');
            }

            if (Schema::hasColumn('live_matches', 'group_key')) {
                $table->dropColumn('group_key');
            }

            if (Schema::hasColumn('live_matches', 'club_user_id')) {
                $table->dropForeign(['club_user_id']);
                $table->dropColumn('club_user_id');
            }

            if (Schema::hasColumn('live_matches', 'visibility')) {
                $table->dropIndex('live_matches_visibility_status_idx');
                $table->dropColumn('visibility');
            }
        });
    }
};
