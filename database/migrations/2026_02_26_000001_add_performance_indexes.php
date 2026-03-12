<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->index(['status', 'city', 'created_at'], 'opportunities_status_city_created_idx');
            $table->index(['team_user_id', 'status', 'created_at'], 'opportunities_team_status_created_idx');
            $table->index(['position', 'created_at'], 'opportunities_position_created_idx');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index(['opportunity_id', 'status', 'created_at'], 'applications_opportunity_status_created_idx');
            $table->index(['player_user_id', 'status', 'created_at'], 'applications_player_status_created_idx');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->index(['to_user_id', 'status', 'created_at'], 'contacts_to_status_created_idx');
            $table->index(['from_user_id', 'status', 'created_at'], 'contacts_from_status_created_idx');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->index(['user_id', 'type', 'created_at'], 'media_user_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('media_user_type_created_idx');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex('contacts_to_status_created_idx');
            $table->dropIndex('contacts_from_status_created_idx');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex('applications_opportunity_status_created_idx');
            $table->dropIndex('applications_player_status_created_idx');
        });

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropIndex('opportunities_status_city_created_idx');
            $table->dropIndex('opportunities_team_status_created_idx');
            $table->dropIndex('opportunities_position_created_idx');
        });
    }
};
