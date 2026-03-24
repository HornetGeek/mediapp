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
        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Doctors::class)->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('blockable_id'); // id للمندوب أو الشركة
            $table->string('blockable_type'); // نوع المحظور (Representative or Company)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_blocks');
    }
};
