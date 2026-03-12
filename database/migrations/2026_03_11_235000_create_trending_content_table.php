<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trending_content')) {
            return;
        }

        Schema::create('trending_content', function (Blueprint $table) {
            $table->id();
            $table->string('trendable_type');
            $table->unsignedBigInteger('trendable_id');
            $table->integer('views_today')->default(0);
            $table->integer('views_week')->default(0);
            $table->integer('views_month')->default(0);
            $table->integer('clicks_today')->default(0);
            $table->integer('clicks_week')->default(0);
            $table->integer('clicks_month')->default(0);
            $table->integer('shares_count')->default(0);
            $table->integer('saves_count')->default(0);
            $table->float('trending_score', 8, 2)->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->date('trending_date')->default(DB::raw('CURRENT_DATE'));
            $table->timestamps();

            $table->index(['trending_score', 'trending_date']);
            $table->index('trendable_type');
            $table->unique(['trendable_type', 'trendable_id', 'trending_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trending_content');
    }
};
