<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Editor role system
            $table->enum('editor_role', [
                'none',
                'contributor',
                'reviewer',
                'senior_reviewer',
                'admin'
            ])->default('none')->after('role');

            // Editor statistics
            $table->integer('contributions_count')->default(0)->after('editor_role');
            $table->integer('approved_contributions')->default(0)->after('contributions_count');
            $table->integer('rejected_contributions')->default(0)->after('approved_contributions');
            $table->decimal('contribution_accuracy', 5, 2)->default(0)->after('rejected_contributions');

            // Trust score
            $table->decimal('trust_score', 5, 2)->default(50)->after('contribution_accuracy');
            $table->timestamp('editor_since')->nullable()->after('trust_score');

            // Review statistics (for reviewers)
            $table->integer('reviews_count')->default(0)->after('editor_since');
            $table->decimal('avg_review_time_hours', 8, 2)->nullable()->after('reviews_count');

            // Permissions
            $table->boolean('can_verify_critical')->default(false)->after('avg_review_time_hours');
            $table->boolean('can_dual_approve')->default(false)->after('can_verify_critical');

            // Indexes
            $table->index('editor_role');
            $table->index('trust_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['editor_role']);
            $table->dropIndex(['trust_score']);

            $table->dropColumn([
                'editor_role',
                'contributions_count',
                'approved_contributions',
                'rejected_contributions',
                'contribution_accuracy',
                'trust_score',
                'editor_since',
                'reviews_count',
                'avg_review_time_hours',
                'can_verify_critical',
                'can_dual_approve',
            ]);
        });
    }
};
