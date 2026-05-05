<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('representatives')) {
            return;
        }

        Schema::table('representatives', function (Blueprint $table) {
            if (!Schema::hasColumn('representatives', 'requested_area_names')) {
                $table->json('requested_area_names')->nullable()->after('requested_line_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('representatives')) {
            return;
        }

        Schema::table('representatives', function (Blueprint $table) {
            if (Schema::hasColumn('representatives', 'requested_area_names')) {
                $table->dropColumn('requested_area_names');
            }
        });
    }
};
