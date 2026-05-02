<?php

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(Specialty::class)->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('total_doctors')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_campaigns');
    }
};
