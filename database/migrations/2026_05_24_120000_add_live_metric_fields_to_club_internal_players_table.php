<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_internal_players', 'aggregate_highlights')) {
                $table->json('aggregate_highlights')->nullable()->after('rating');
            }
            if (! Schema::hasColumn('club_internal_players', 'last_match_highlights')) {
                $table->json('last_match_highlights')->nullable()->after('aggregate_highlights');
            }
            if (! Schema::hasColumn('club_internal_players', 'last_match_rating')) {
                $table->string('last_match_rating', 20)->nullable()->after('last_match_highlights');
            }
            if (! Schema::hasColumn('club_internal_players', 'last_match_summary')) {
                $table->text('last_match_summary')->nullable()->after('last_match_rating');
            }
            if (! Schema::hasColumn('club_internal_players', 'last_match_date')) {
                $table->string('last_match_date', 80)->nullable()->after('last_match_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_internal_players', function (Blueprint $table): void {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('club_internal_players', 'aggregate_highlights') ? 'aggregate_highlights' : null,
                Schema::hasColumn('club_internal_players', 'last_match_highlights') ? 'last_match_highlights' : null,
                Schema::hasColumn('club_internal_players', 'last_match_rating') ? 'last_match_rating' : null,
                Schema::hasColumn('club_internal_players', 'last_match_summary') ? 'last_match_summary' : null,
                Schema::hasColumn('club_internal_players', 'last_match_date') ? 'last_match_date' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
