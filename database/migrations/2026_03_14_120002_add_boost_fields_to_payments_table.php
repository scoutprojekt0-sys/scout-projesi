<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('boost_package_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
            $table->string('payment_context')->default('subscription')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boost_package_id');
            $table->dropColumn('payment_context');
        });
    }
};
