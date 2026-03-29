<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupe_key')->nullable()->after('target_type');
            $table->index('dedupe_key', 'notifications_dedupe_key_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_dedupe_key_index');
            $table->dropColumn('dedupe_key');
        });
    }
};
