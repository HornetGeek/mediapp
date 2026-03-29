<?php

use App\Models\AppVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->string('platform')
                ->default(AppVersion::PLATFORM_BOTH)
                ->after('app_type');
        });

        DB::table('app_versions')->update([
            'platform' => AppVersion::PLATFORM_BOTH,
        ]);

        $duplicateAppTypes = DB::table('app_versions')
            ->select('app_type')
            ->groupBy('app_type')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('app_type');

        foreach ($duplicateAppTypes as $appType) {
            $latestId = DB::table('app_versions')
                ->where('app_type', $appType)
                ->max('id');

            DB::table('app_versions')
                ->where('app_type', $appType)
                ->where('id', '!=', $latestId)
                ->delete();
        }

        Schema::table('app_versions', function (Blueprint $table) {
            $table->unique(['app_type', 'platform'], 'app_versions_app_type_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropUnique('app_versions_app_type_platform_unique');
            $table->dropColumn('platform');
        });
    }
};
