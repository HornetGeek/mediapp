<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $specialty = DB::table('specialties')->where('name', 'Orthopedic')->first();

        if ($specialty) {
            DB::table('specialties')
                ->where('id', $specialty->id)
                ->update([
                    'slug' => 'orthopedic',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('specialties')->insert([
            'name' => 'Orthopedic',
            'slug' => 'orthopedic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('specialties')
            ->where('name', 'Orthopedic')
            ->where('slug', 'orthopedic')
            ->delete();
    }
};
