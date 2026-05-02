<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_broadcasts')) {
            return;
        }

        if (!Schema::hasColumn('notification_broadcasts', 'video_path')) {
            Schema::table('notification_broadcasts', function (Blueprint $table) {
                $table->string('video_path')->nullable()->after('image_path');
            });
        }

        if (!Schema::hasColumn('notification_broadcasts', 'media_type')) {
            Schema::table('notification_broadcasts', function (Blueprint $table) {
                $table->enum('media_type', ['none', 'image', 'video'])->default('none')->after('video_path');
            });
        }

        if (!Schema::hasColumn('notification_broadcasts', 'delivery_type')) {
            Schema::table('notification_broadcasts', function (Blueprint $table) {
                $table->enum('delivery_type', ['both', 'push_only', 'in_app_only'])->default('both')->after('media_type');
            });
        }

        if (!Schema::hasColumn('notification_broadcasts', 'display_type')) {
            Schema::table('notification_broadcasts', function (Blueprint $table) {
                $table->enum('display_type', ['list', 'modal'])->default('list')->after('delivery_type');
            });
        }

        if (!Schema::hasColumn('notification_broadcasts', 'is_skippable')) {
            Schema::table('notification_broadcasts', function (Blueprint $table) {
                $table->boolean('is_skippable')->default(true)->after('display_type');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_broadcasts')) {
            return;
        }

        foreach (['is_skippable', 'display_type', 'delivery_type', 'media_type', 'video_path'] as $column) {
            if (Schema::hasColumn('notification_broadcasts', $column)) {
                Schema::table('notification_broadcasts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
