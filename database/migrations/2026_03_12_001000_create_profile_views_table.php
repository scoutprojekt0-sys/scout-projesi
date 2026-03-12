<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profile_views')) {
            return;
        }

        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('viewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['viewed_user_id', 'viewed_at']);
            $table->index('viewer_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_views');
    }
};
