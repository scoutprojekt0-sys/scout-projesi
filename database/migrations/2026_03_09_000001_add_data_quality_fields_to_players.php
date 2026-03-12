<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('source_url')->nullable()->after('phone');
            $table->decimal('confidence_score', 3, 2)->default(0.5)->after('source_url');
            $table->timestamp('verified_at')->nullable()->after('confidence_score');
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'needs_review'])->default('pending')->after('verified_at');
            $table->text('verification_notes')->nullable()->after('verification_status');
            $table->unsignedBigInteger('last_updated_by')->nullable()->after('verification_notes');
            $table->integer('data_version')->default(1)->after('last_updated_by');
            $table->boolean('has_source')->default(false)->after('data_version');
            $table->boolean('has_conflicts')->default(false)->after('has_source');

            $table->foreign('last_updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['verification_status', 'confidence_score']);
            $table->index('has_source');
            $table->index('has_conflicts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['last_updated_by']);
            $table->dropIndex(['verification_status', 'confidence_score']);
            $table->dropIndex(['has_source']);
            $table->dropIndex(['has_conflicts']);

            $table->dropColumn([
                'source_url',
                'confidence_score',
                'verified_at',
                'verification_status',
                'verification_notes',
                'last_updated_by',
                'data_version',
                'has_source',
                'has_conflicts'
            ]);
        });
    }
};
