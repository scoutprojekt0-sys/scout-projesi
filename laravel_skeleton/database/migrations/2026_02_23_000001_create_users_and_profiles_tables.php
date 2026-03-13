<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['player', 'manager', 'coach', 'scout', 'team']);
            $table->string('city', 80)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('player_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('position', 40)->nullable();
            $table->string('dominant_foot', 10)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->text('bio')->nullable();
            $table->string('current_team', 120)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('team_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('team_name', 140);
            $table->string('league_level', 60)->nullable();
            $table->string('city', 80)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->text('needs_text')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->enum('role_type', ['manager', 'coach', 'scout']);
            $table->string('organization', 140)->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
            $table->text('bio')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['video', 'image']);
            $table->string('url');
            $table->string('thumb_url')->nullable();
            $table->string('title', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('team_profiles');
        Schema::dropIfExists('player_profiles');
        Schema::dropIfExists('users');
    }
};
