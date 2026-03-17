<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('player_match_schedules')) {
            Schema::create('player_match_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('match_title', 160);
                $table->string('team_name', 120)->nullable();
                $table->string('opponent_name', 120)->nullable();
                $table->string('position', 60)->nullable();
                $table->timestamp('match_date');
                $table->string('city', 80);
                $table->string('district', 80)->nullable();
                $table->string('venue', 160)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_public')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['player_user_id', 'match_date']);
                $table->index(['city', 'district', 'match_date']);
                $table->index(['position', 'match_date']);
            });
        }

        if (! Schema::hasTable('live_watch_requests')) {
            Schema::create('live_watch_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('requester_role', 20);
                $table->date('target_date');
                $table->string('city', 80);
                $table->string('district', 80)->nullable();
                $table->string('position', 60)->nullable();
                $table->unsignedTinyInteger('radius_km')->default(20);
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('open');
                $table->timestamps();

                $table->index(['requester_user_id', 'target_date']);
                $table->index(['city', 'district', 'target_date']);
                $table->index(['position', 'target_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_watch_requests');
        Schema::dropIfExists('player_match_schedules');
    }
};
