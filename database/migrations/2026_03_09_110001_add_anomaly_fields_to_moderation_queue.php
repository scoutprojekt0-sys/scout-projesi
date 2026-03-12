<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moderation_queue', function (Blueprint $table) {
            $table->decimal('anomaly_score', 3, 2)->nullable()->default(0);
            $table->decimal('risk_score', 3, 2)->nullable()->default(0);
            $table->boolean('has_conflicts')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('moderation_queue', function (Blueprint $table) {
            $table->dropColumn(['anomaly_score', 'risk_score', 'has_conflicts']);
        });
    }
};
