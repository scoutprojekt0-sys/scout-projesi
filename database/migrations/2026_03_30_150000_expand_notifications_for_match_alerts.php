<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable()->after('type');
            }

            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }

            if (! Schema::hasColumn('notifications', 'priority')) {
                $table->string('priority', 16)->default('low')->after('payload');
            }

            if (! Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }

            if (! Schema::hasColumn('notifications', 'related_player_id')) {
                $table->foreignId('related_player_id')->nullable()->after('read_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'related_match_schedule_id')) {
                $table->foreignId('related_match_schedule_id')->nullable()->after('related_player_id')->constrained('player_match_schedules')->nullOnDelete();
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read'], 'notifications_user_read_idx');
            $table->index(['user_id', 'priority'], 'notifications_user_priority_idx');
            $table->index('related_player_id', 'notifications_related_player_idx');
            $table->index('related_match_schedule_id', 'notifications_related_schedule_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_idx');
            $table->dropIndex('notifications_user_priority_idx');
            $table->dropIndex('notifications_related_player_idx');
            $table->dropIndex('notifications_related_schedule_idx');

            if (Schema::hasColumn('notifications', 'related_match_schedule_id')) {
                $table->dropConstrainedForeignId('related_match_schedule_id');
            }

            if (Schema::hasColumn('notifications', 'related_player_id')) {
                $table->dropConstrainedForeignId('related_player_id');
            }

            if (Schema::hasColumn('notifications', 'read_at')) {
                $table->dropColumn('read_at');
            }

            if (Schema::hasColumn('notifications', 'priority')) {
                $table->dropColumn('priority');
            }

            if (Schema::hasColumn('notifications', 'message')) {
                $table->dropColumn('message');
            }

            if (Schema::hasColumn('notifications', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
