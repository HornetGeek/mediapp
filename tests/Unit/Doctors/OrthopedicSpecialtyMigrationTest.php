<?php

namespace Tests\Unit\Doctors;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrthopedicSpecialtyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_migration_adds_orthopedic_specialty_idempotently(): void
    {
        Schema::dropIfExists('specialties');

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $migration = require base_path('database/migrations/2026_05_05_000000_add_orthopedic_specialty.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('specialties')->where('name', 'Orthopedic')->count());

        $specialty = DB::table('specialties')->where('name', 'Orthopedic')->first();

        $this->assertSame('orthopedic', $specialty->slug);
    }
}
