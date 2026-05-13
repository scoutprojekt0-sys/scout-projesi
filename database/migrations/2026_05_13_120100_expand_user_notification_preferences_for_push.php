<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            $table->boolean('allow_push_notifications')->default(true)->after('allow_match_alerts');
            $table->boolean('allow_inbox_push')->default(true)->after('allow_push_notifications');
            $table->boolean('allow_offer_alerts')->default(true)->after('allow_inbox_push');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'allow_push_notifications',
                'allow_inbox_push',
                'allow_offer_alerts',
            ]);
        });
    }
};
