<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_profiles') || Schema::hasColumn('player_profiles', 'featured_video_clip_id')) {
            return;
        }

        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->foreignId('featured_video_clip_id')
                ->nullable()
                ->after('current_team')
                ->constrained('video_clips')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('player_profiles') || ! Schema::hasColumn('player_profiles', 'featured_video_clip_id')) {
            return;
        }

        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('featured_video_clip_id');
        });
    }
};
