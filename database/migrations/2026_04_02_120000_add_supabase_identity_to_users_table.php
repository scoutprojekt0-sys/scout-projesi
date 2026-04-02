<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('supabase_user_id', 190)->nullable()->unique()->after('email');
            $table->string('auth_provider', 30)->default('laravel')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['supabase_user_id']);
            $table->dropColumn(['supabase_user_id', 'auth_provider']);
        });
    }
};
