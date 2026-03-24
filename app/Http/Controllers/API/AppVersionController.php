<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'app_type' => 'required',
        ]);

        $latest = AppVersion::where('app_type', $request->app_type)
            ->latest()
            ->first();

        // dd($latest);

        if (!$latest) {
            return ApiResponse::sendResponse(404, 'No version found for this application', []);
        }

        // if (version_compare($request->version, $latest->version, '<')) {

        $data = [
            'isForced' => (bool) $latest->is_forced,
            'version' => $latest->version,
            // 'storeUrl' => $latest->store_url,
        ];
        return ApiResponse::sendResponse(200, 'New version available', $data);
        // }

        // return ApiResponse::sendResponse(200, 'Application is up to date', [
        //     'hasUpdate' => false,
        // ]);
    }
}
