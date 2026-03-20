<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_analyses', function (Blueprint $table) {
            $table->string('provider', 40)->default('mock')->after('analysis_type');
            $table->string('external_job_id', 120)->nullable()->after('provider');
            $table->string('worker_status', 60)->nullable()->after('external_job_id');
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');

            $table->index(['provider', 'worker_status']);
            $table->index(['external_job_id']);
        });
    }

    public function down(): void
    {
        Schema::table('video_analyses', function (Blueprint $table) {
            $table->dropIndex(['provider', 'worker_status']);
            $table->dropIndex(['external_job_id']);
            $table->dropColumn([
                'provider',
                'external_job_id',
                'worker_status',
                'submitted_at',
                'failed_at',
            ]);
        });
    }
};
