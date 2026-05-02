<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('push_notification_campaigns') || Schema::hasColumn('push_notification_campaigns', 'delivery_type')) {
            return;
        }

        Schema::table('push_notification_campaigns', function (Blueprint $table) {
            $table->enum('delivery_type', ['both', 'push_only', 'in_app_only'])
                ->default('both')
                ->after('media_type');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('push_notification_campaigns') || !Schema::hasColumn('push_notification_campaigns', 'delivery_type')) {
            return;
        }

        Schema::table('push_notification_campaigns', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
