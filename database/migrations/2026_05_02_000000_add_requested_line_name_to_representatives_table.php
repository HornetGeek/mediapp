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
            if (!Schema::hasColumn('representatives', 'requested_line_name')) {
                $table->string('requested_line_name')->nullable()->after('requested_company_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('representatives')) {
            return;
        }

        Schema::table('representatives', function (Blueprint $table) {
            if (Schema::hasColumn('representatives', 'requested_line_name')) {
                $table->dropColumn('requested_line_name');
            }
        });
    }
};
