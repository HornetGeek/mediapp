<?php

use App\Models\RepCompanyCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('representatives')
            ->where('registration_status', 'pending')
            ->orderBy('id')
            ->chunkById(100, function ($representatives) {
                foreach ($representatives as $representative) {
                    DB::transaction(function () use ($representative) {
                        $catalogId = $representative->company_catalog_id;
                        $companyName = trim((string) $representative->requested_company_name);

                        if (!$representative->company_id && !$catalogId && $companyName !== '') {
                            $catalog = RepCompanyCatalog::firstOrCreate(
                                ['normalized_name' => RepCompanyCatalog::normalizeName($companyName)],
                                ['name' => $companyName, 'status' => 'active']
                            );

                            if ($catalog->status !== 'active') {
                                $catalog->update(['status' => 'active']);
                            }

                            $catalogId = $catalog->id;
                        }

                        DB::table('representatives')
                            ->where('id', $representative->id)
                            ->where('registration_status', 'pending')
                            ->update([
                                'company_catalog_id' => $catalogId,
                                'requested_company_name' => $catalogId ? null : $representative->requested_company_name,
                                'registration_status' => 'active',
                                'daily_visits_limit' => $representative->daily_visits_limit
                                    ?? config('reps.self_registered_daily_visits_limit'),
                                'updated_at' => now(),
                            ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Activated registrations and shared catalog entries cannot be safely reversed.
    }
};
