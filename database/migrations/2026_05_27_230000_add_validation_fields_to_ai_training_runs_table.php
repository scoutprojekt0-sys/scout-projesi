<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_training_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_training_runs', 'validation_summary')) {
                $table->json('validation_summary')->nullable()->after('output_log');
            }
            if (! Schema::hasColumn('ai_training_runs', 'validation_passed')) {
                $table->boolean('validation_passed')->nullable()->after('validation_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_training_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_training_runs', 'validation_passed')) {
                $table->dropColumn('validation_passed');
            }
            if (Schema::hasColumn('ai_training_runs', 'validation_summary')) {
                $table->dropColumn('validation_summary');
            }
        });
    }
};
