<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('scout_points')->default(0)->after('trust_score');
            $table->integer('scout_tips_count')->default(0)->after('scout_points');
            $table->integer('successful_tips_count')->default(0)->after('scout_tips_count');
            $table->decimal('scout_accuracy_rate', 5, 2)->default(0)->after('successful_tips_count');
            $table->string('scout_rank', 40)->default('rookie')->after('scout_accuracy_rate');

            $table->index('scout_points');
            $table->index('scout_rank');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['scout_points']);
            $table->dropIndex(['scout_rank']);
            $table->dropColumn([
                'scout_points',
                'scout_tips_count',
                'successful_tips_count',
                'scout_accuracy_rate',
                'scout_rank',
            ]);
        });
    }
};
