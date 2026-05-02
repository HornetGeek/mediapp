<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerAdResource;
use App\Models\BannerAd;

class BannerAdController extends Controller
{
    public function index()
    {
        $bannerAds = BannerAd::active()
            ->orderBy('sort_order')
            ->latest()
            ->get();

        return ApiResponse::sendResponse(
            200,
            'Banner ads retrieved successfully',
            BannerAdResource::collection($bannerAds)
        );
    }
}
