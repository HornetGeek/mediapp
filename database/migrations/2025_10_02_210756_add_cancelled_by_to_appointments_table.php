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
        if (Schema::hasColumn('appointments', 'cancelled_by')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('cancelled_by')->nullable()->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('appointments', 'cancelled_by')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('cancelled_by');
        });
    }
};
