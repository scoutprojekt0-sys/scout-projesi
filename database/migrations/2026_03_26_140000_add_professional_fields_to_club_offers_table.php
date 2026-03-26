<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_offers', function (Blueprint $table) {
            $table->string('offer_type', 40)->default('permanent')->after('player_name');
            $table->string('currency', 3)->default('EUR')->after('amount_eur');
            $table->string('season', 20)->nullable()->after('currency');
            $table->unsignedTinyInteger('contract_years')->nullable()->after('season');
            $table->decimal('salary_amount', 12, 2)->nullable()->after('contract_years');
            $table->decimal('signing_fee', 12, 2)->nullable()->after('salary_amount');
            $table->string('bonus_summary', 255)->nullable()->after('signing_fee');
            $table->date('contract_start_date')->nullable()->after('bonus_summary');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->dateTime('expires_at')->nullable()->after('contract_end_date');
            $table->text('clauses')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('club_offers', function (Blueprint $table) {
            $table->dropColumn([
                'offer_type',
                'currency',
                'season',
                'contract_years',
                'salary_amount',
                'signing_fee',
                'bonus_summary',
                'contract_start_date',
                'contract_end_date',
                'expires_at',
                'clauses',
            ]);
        });
    }
};
