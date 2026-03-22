<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_profiles', 'branch')) {
                $table->string('branch', 120)->nullable()->after('role_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('staff_profiles', 'branch')) {
                $table->dropColumn('branch');
            }
        });
    }
};
