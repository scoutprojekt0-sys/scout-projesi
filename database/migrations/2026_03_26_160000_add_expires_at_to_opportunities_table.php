<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            if (! Schema::hasColumn('opportunities', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('status');
                $table->index(['status', 'expires_at'], 'opportunities_status_expires_at_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            if (Schema::hasColumn('opportunities', 'expires_at')) {
                $table->dropIndex('opportunities_status_expires_at_idx');
                $table->dropColumn('expires_at');
            }
        });
    }
};
