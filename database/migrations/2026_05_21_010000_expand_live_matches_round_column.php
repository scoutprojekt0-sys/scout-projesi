<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            if (Schema::hasColumn('live_matches', 'round')) {
                $table->text('round')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_matches', function (Blueprint $table): void {
            if (Schema::hasColumn('live_matches', 'round')) {
                $table->string('round', 255)->nullable()->change();
            }
        });
    }
};
