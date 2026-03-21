<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_transfers', function (Blueprint $table) {
            $table->enum('negotiation_status', ['open', 'countered', 'accepted', 'rejected'])
                ->default('open')
                ->after('verification_status');
            $table->decimal('counter_fee', 15, 2)->nullable()->after('fee');
            $table->text('negotiation_notes')->nullable()->after('notes');
            $table->unsignedBigInteger('negotiation_updated_by')->nullable()->after('verified_by');
            $table->timestamp('negotiation_updated_at')->nullable()->after('verified_at');

            $table->index('negotiation_status');
        });
    }

    public function down(): void
    {
        Schema::table('player_transfers', function (Blueprint $table) {
            $table->dropIndex(['negotiation_status']);
            $table->dropColumn([
                'negotiation_status',
                'counter_fee',
                'negotiation_notes',
                'negotiation_updated_by',
                'negotiation_updated_at',
            ]);
        });
    }
};
