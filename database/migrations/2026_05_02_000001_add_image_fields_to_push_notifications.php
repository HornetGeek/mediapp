<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_notification_campaigns') && !Schema::hasColumn('push_notification_campaigns', 'image_path')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('body');
            });
        }

        if (Schema::hasTable('push_notification_campaigns') && !Schema::hasColumn('push_notification_campaigns', 'video_path')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->string('video_path')->nullable()->after('image_path');
            });
        }

        if (Schema::hasTable('push_notification_campaigns') && !Schema::hasColumn('push_notification_campaigns', 'display_type')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->enum('display_type', ['list', 'modal'])->default('list')->after('video_path');
            });
        }

        if (Schema::hasTable('push_notification_campaigns') && !Schema::hasColumn('push_notification_campaigns', 'is_skippable')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->boolean('is_skippable')->default(true)->after('display_type');
            });
        }

        if (Schema::hasTable('push_notification_campaigns') && !Schema::hasColumn('push_notification_campaigns', 'media_type')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->enum('media_type', ['none', 'image', 'video'])->default('none')->after('is_skippable');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'image_url')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('image_url')->nullable()->after('body');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'video_url')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('video_url')->nullable()->after('image_url');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'media_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->enum('media_type', ['none', 'image', 'video'])->default('none')->after('video_url');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'display_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->enum('display_type', ['list', 'modal'])->default('list')->after('media_type');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'is_skippable')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->boolean('is_skippable')->default(true)->after('display_type');
            });
        }

        if (Schema::hasTable('notifications') && !Schema::hasColumn('notifications', 'acknowledged_at')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->timestamp('acknowledged_at')->nullable()->after('is_skippable');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'image_url')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }

        if (Schema::hasTable('notifications')) {
            foreach (['video_url', 'media_type', 'display_type', 'is_skippable', 'acknowledged_at'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    Schema::table('notifications', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('push_notification_campaigns') && Schema::hasColumn('push_notification_campaigns', 'image_path')) {
            Schema::table('push_notification_campaigns', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }

        if (Schema::hasTable('push_notification_campaigns')) {
            foreach (['video_path', 'display_type', 'is_skippable', 'media_type'] as $column) {
                if (Schema::hasColumn('push_notification_campaigns', $column)) {
                    Schema::table('push_notification_campaigns', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
