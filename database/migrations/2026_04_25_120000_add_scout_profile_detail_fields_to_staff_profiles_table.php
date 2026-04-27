<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_profiles', 'focus')) {
                $table->text('focus')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('staff_profiles', 'coverage')) {
                $table->text('coverage')->nullable()->after('focus');
            }

            if (! Schema::hasColumn('staff_profiles', 'scouting_notes')) {
                $table->text('scouting_notes')->nullable()->after('coverage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['focus', 'coverage', 'scouting_notes'] as $column) {
                if (Schema::hasColumn('staff_profiles', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
