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
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 20)->nullable()->after('sport');
            }
            if (! Schema::hasColumn('users', 'contract_status')) {
                $table->string('contract_status', 20)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'seeking_club')) {
                $table->boolean('seeking_club')->nullable()->after('contract_status');
            }
        });

        DB::table('users')
            ->where('role', 'player')
            ->whereNull('gender')
            ->update(['gender' => 'bay']);

        DB::table('users')
            ->where('role', 'player')
            ->whereNull('contract_status')
            ->update(['contract_status' => 'active']);

        DB::table('users')
            ->where('role', 'player')
            ->whereNull('seeking_club')
            ->update(['seeking_club' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['seeking_club', 'contract_status', 'gender'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
