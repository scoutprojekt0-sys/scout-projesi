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
            if (! Schema::hasColumn('users', 'sport')) {
                $table->string('sport', 40)->nullable()->after('role');
            }
        });

        DB::table('users')
            ->where('role', 'player')
            ->whereNull('sport')
            ->update(['sport' => 'futbol']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'sport')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('sport');
            });
        }
    }
};
