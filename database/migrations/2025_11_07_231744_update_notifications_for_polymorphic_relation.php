<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        if (!Schema::hasColumn('notifications', 'notifiable_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('notifiable_id')->nullable();
            });
        }

        if (!Schema::hasColumn('notifications', 'notifiable_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('notifiable_type')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasColumn('notifications', 'notifiable_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('notifiable_id');
            });
        }

        if (Schema::hasColumn('notifications', 'notifiable_type')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('notifiable_type');
            });
        }
    }
};
