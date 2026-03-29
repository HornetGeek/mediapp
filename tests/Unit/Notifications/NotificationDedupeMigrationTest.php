<?php

namespace Tests\Unit\Notifications;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationDedupeMigrationTest extends TestCase
{
    public function test_migration_adds_dedupe_key_column_and_index(): void
    {
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->enum('target_type', ['doctor', 'reps', 'company'])->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->timestamps();
        });

        $migration = require base_path('database/migrations/2026_03_29_220000_add_dedupe_key_to_notifications_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('notifications', 'dedupe_key'));

        $indexes = collect(DB::select("PRAGMA index_list('notifications')"))
            ->pluck('name')
            ->all();

        $this->assertContains('notifications_dedupe_key_index', $indexes);
    }
}
