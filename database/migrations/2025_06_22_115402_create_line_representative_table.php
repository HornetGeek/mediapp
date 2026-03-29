<?php

use App\Models\Line;
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
        if (Schema::hasTable('line_representative')) {
            return;
        }

        Schema::create('line_representative', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Representative::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Line::class)->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_representative');
    }
};
