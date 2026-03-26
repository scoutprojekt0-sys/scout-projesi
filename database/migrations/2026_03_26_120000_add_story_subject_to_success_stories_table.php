<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('success_stories') || Schema::hasColumn('success_stories', 'story_subject')) {
            return;
        }

        Schema::table('success_stories', function (Blueprint $table) {
            $table->string('story_subject', 150)->nullable()->after('sport');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('success_stories') || ! Schema::hasColumn('success_stories', 'story_subject')) {
            return;
        }

        Schema::table('success_stories', function (Blueprint $table) {
            $table->dropColumn('story_subject');
        });
    }
};
