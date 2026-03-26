<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_team_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('group_key', 40);
            $table->string('name', 80);
            $table->string('note', 255)->nullable();
            $table->boolean('is_showcased')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['club_user_id', 'group_key']);
            $table->index(['club_user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_team_groups');
    }
};
