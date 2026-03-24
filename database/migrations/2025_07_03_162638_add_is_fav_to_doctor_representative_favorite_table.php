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
        Schema::table('doctor_representative_favorite', function (Blueprint $table) {
            $table->boolean('is_fav')->default(false)->after('doctors_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_representative_favorite', function (Blueprint $table) {
            $table->dropColumn('is_fav');
        });
    }
};
