<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('representatives')) {
            return;
        }

        if (!Schema::hasColumn('representatives', 'fcm_token')) {
            Schema::table('representatives', function (Blueprint $table) {
                $table->text('fcm_token')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('representatives') && Schema::hasColumn('representatives', 'fcm_token')) {
            Schema::table('representatives', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
