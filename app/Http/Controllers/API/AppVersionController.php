<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'app_type' => ['required', Rule::in(AppVersion::SUPPORTED_APP_TYPES)],
            'platform' => ['nullable', Rule::in([
                AppVersion::PLATFORM_ANDROID,
                AppVersion::PLATFORM_IOS,
            ])],
        ]);

        $appType = $request->string('app_type')->toString();
        $requestedPlatform = $request->string('platform')->toString();

        if ($requestedPlatform !== '') {
            $latest = AppVersion::where('app_type', $appType)
                ->where('platform', $requestedPlatform)
                ->first();

            if (!$latest) {
                $latest = AppVersion::where('app_type', $appType)
                    ->where('platform', AppVersion::PLATFORM_BOTH)
                    ->first();
            }
        } else {
            $latest = AppVersion::where('app_type', $appType)
                ->where('platform', AppVersion::PLATFORM_BOTH)
                ->first();
        }

        if (!$latest) {
            return ApiResponse::sendResponse(404, 'No version found for this application', []);
        }

        $data = [
            'isForced' => (bool) $latest->is_forced,
            'version' => $latest->version,
            'platform' => $latest->platform,
        ];
        return ApiResponse::sendResponse(200, 'New version available', $data);
    }
}
