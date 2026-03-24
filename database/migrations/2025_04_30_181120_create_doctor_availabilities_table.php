<?php

use App\Models\Doctors;
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
        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Doctors::class)->constrained()->onDelete('cascade');
            $table->date('date'); 
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_availabilities');
    }
};
