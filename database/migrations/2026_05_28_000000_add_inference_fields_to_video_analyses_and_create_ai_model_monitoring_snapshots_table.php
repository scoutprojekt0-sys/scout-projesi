<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_analyses', function (Blueprint $table): void {
            if (! Schema::hasColumn('video_analyses', 'inference_sport')) {
                $table->string('inference_sport', 40)->nullable()->after('analysis_version');
            }
            if (! Schema::hasColumn('video_analyses', 'inference_model_version')) {
                $table->string('inference_model_version', 120)->nullable()->after('inference_sport');
            }
            if (! Schema::hasColumn('video_analyses', 'inference_model_path')) {
                $table->string('inference_model_path', 500)->nullable()->after('inference_model_version');
            }
        });

        if (! Schema::hasTable('ai_model_monitoring_snapshots')) {
            Schema::create('ai_model_monitoring_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->string('sport', 40);
                $table->string('model_version', 120);
                $table->unsignedInteger('sample_count')->default(0);
                $table->timestamp('window_started_at')->nullable();
                $table->timestamp('window_ended_at')->nullable();
                $table->json('metric_summary')->nullable();
                $table->json('drift_summary')->nullable();
                $table->boolean('drift_detected')->default(false);
                $table->boolean('auto_rollback_executed')->default(false);
                $table->string('rollback_target_model_version', 120)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();

                $table->index(['sport', 'model_version', 'captured_at'], 'ai_model_monitoring_sport_model_captured_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_monitoring_snapshots');

        Schema::table('video_analyses', function (Blueprint $table): void {
            if (Schema::hasColumn('video_analyses', 'inference_model_path')) {
                $table->dropColumn('inference_model_path');
            }
            if (Schema::hasColumn('video_analyses', 'inference_model_version')) {
                $table->dropColumn('inference_model_version');
            }
            if (Schema::hasColumn('video_analyses', 'inference_sport')) {
                $table->dropColumn('inference_sport');
            }
        });
    }
};
