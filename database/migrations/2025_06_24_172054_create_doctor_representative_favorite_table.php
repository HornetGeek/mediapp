<?php

use App\Models\Doctors;
use App\Models\Representative;
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
        if (Schema::hasTable('doctor_representative_favorite')) {
            return;
        }

        Schema::create('doctor_representative_favorite', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Representative::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Doctors::class)->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_representative_favorite');
    }
};
