<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (!Schema::hasColumn('notifications', 'dedupe_fingerprint')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->char('dedupe_fingerprint', 64)->nullable()->after('dedupe_key');
                $table->unique('dedupe_fingerprint', 'notifications_dedupe_fingerprint_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasColumn('notifications', 'dedupe_fingerprint')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropUnique('notifications_dedupe_fingerprint_unique');
                $table->dropColumn('dedupe_fingerprint');
            });
        }
    }
};
