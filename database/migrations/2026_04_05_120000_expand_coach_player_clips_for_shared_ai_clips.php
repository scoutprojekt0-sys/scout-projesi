<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_player_clips', function (Blueprint $table) {
            $table->foreignId('video_clip_id')->nullable()->after('player_name')->constrained('video_clips')->nullOnDelete();
            $table->foreignId('video_analysis_id')->nullable()->after('video_clip_id')->constrained('video_analyses')->nullOnDelete();
            $table->unsignedInteger('start_second')->nullable()->after('second_mark');
            $table->unsignedInteger('end_second')->nullable()->after('start_second');
            $table->string('range_label', 32)->nullable()->after('stamp');
            $table->json('shared_roles')->nullable()->after('range_label');
            $table->json('analysis_summary')->nullable()->after('shared_roles');

            $table->index(['video_clip_id', 'created_at']);
            $table->index(['video_analysis_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('coach_player_clips', function (Blueprint $table) {
            $table->dropIndex(['video_clip_id', 'created_at']);
            $table->dropIndex(['video_analysis_id', 'created_at']);
            $table->dropConstrainedForeignId('video_analysis_id');
            $table->dropConstrainedForeignId('video_clip_id');
            $table->dropColumn([
                'start_second',
                'end_second',
                'range_label',
                'shared_roles',
                'analysis_summary',
            ]);
        });
    }
};
