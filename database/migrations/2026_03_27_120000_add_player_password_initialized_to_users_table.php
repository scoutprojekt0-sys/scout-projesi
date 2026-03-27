<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'player_password_initialized')) {
                $table->boolean('player_password_initialized')->default(true)->after('email_verification_token');
            }
        });

        DB::table('users')
            ->where('role', 'player')
            ->update(['player_password_initialized' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'player_password_initialized')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('player_password_initialized');
        });
    }
};
