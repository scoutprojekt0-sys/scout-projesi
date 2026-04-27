<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_transfers', function (Blueprint $table) {
            $table->string('from_club_name', 160)->nullable()->after('from_club_id');
            $table->string('to_club_name', 160)->nullable()->after('to_club_id');
            $table->unsignedBigInteger('to_club_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('player_transfers', function (Blueprint $table) {
            $table->dropColumn(['from_club_name', 'to_club_name']);
            $table->unsignedBigInteger('to_club_id')->nullable(false)->change();
        });
    }
};
