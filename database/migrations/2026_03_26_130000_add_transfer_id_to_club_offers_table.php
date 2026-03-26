<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_offers') || Schema::hasColumn('club_offers', 'transfer_id')) {
            return;
        }

        Schema::table('club_offers', function (Blueprint $table) {
            $table->foreignId('transfer_id')->nullable()->after('club_user_id')->constrained('player_transfers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('club_offers') || ! Schema::hasColumn('club_offers', 'transfer_id')) {
            return;
        }

        Schema::table('club_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfer_id');
        });
    }
};
