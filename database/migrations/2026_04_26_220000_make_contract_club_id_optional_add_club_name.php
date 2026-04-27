<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('club_name', 160)->nullable()->after('club_id');
            $table->unsignedBigInteger('club_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('club_name');
            $table->unsignedBigInteger('club_id')->nullable(false)->change();
        });
    }
};
