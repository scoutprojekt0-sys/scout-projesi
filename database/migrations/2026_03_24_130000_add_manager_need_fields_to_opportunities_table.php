<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            if (! Schema::hasColumn('opportunities', 'min_height')) {
                $table->unsignedSmallInteger('min_height')->nullable()->after('age_max');
            }
            if (! Schema::hasColumn('opportunities', 'dominant_side')) {
                $table->string('dominant_side', 20)->nullable()->after('min_height');
            }
            if (! Schema::hasColumn('opportunities', 'free_only')) {
                $table->string('free_only', 20)->nullable()->after('dominant_side');
            }
            if (! Schema::hasColumn('opportunities', 'budget_min')) {
                $table->unsignedInteger('budget_min')->nullable()->after('free_only');
            }
            if (! Schema::hasColumn('opportunities', 'budget_max')) {
                $table->unsignedInteger('budget_max')->nullable()->after('budget_min');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $drops = [];
            foreach (['min_height', 'dominant_side', 'free_only', 'budget_min', 'budget_max'] as $column) {
                if (Schema::hasColumn('opportunities', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
