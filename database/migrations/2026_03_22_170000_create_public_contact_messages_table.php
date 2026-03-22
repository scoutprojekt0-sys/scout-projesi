<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('email', 120);
            $table->text('message');
            $table->string('source', 40)->default('homepage');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_contact_messages');
    }
};
