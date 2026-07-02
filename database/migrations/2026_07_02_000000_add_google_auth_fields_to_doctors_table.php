<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('doctors')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('email');
            }

            if (!Schema::hasColumn('doctors', 'google_avatar')) {
                $table->text('google_avatar')->nullable()->after('google_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('doctors')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'google_id')) {
                $table->dropUnique(['google_id']);
                $table->dropColumn('google_id');
            }

            if (Schema::hasColumn('doctors', 'google_avatar')) {
                $table->dropColumn('google_avatar');
            }
        });
    }
};
