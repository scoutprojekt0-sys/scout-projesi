<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_internal_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('profile_type', 40)->default('internal_profile');
            $table->string('visibility', 40)->default('club_only');
            $table->string('group_key', 40);
            $table->string('name', 120);
            $table->string('gender', 40)->nullable();
            $table->string('sport', 40)->nullable();
            $table->string('birth_year', 20)->nullable();
            $table->string('age', 20)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('height', 40)->nullable();
            $table->string('shirt_number', 20)->nullable();
            $table->string('contract_status', 40)->nullable();
            $table->string('contact', 120)->nullable();
            $table->string('dominant_foot', 40)->nullable();
            $table->text('bio')->nullable();
            $table->text('note')->nullable();
            $table->string('matches', 20)->nullable();
            $table->string('minutes', 20)->nullable();
            $table->string('goals', 20)->nullable();
            $table->string('assists', 20)->nullable();
            $table->string('rating', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_internal_players');
    }
};
