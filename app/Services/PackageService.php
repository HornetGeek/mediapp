<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Http\Resources\PackageResource;
use App\Models\Package;

class PackageService
{
    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService
    ) {
    }

    public function createPackage(array $data)
    {
        $normalizedPlan = $this->subscriptionPlanService->normalizePackageAttributes($data);

        $package = Package::create([
            'name' => $data['name'],
            'price' => $data['price'],
            'duration' => $normalizedPlan['duration'],
            'plan_type' => $normalizedPlan['plan_type'],
            'billing_months' => $normalizedPlan['billing_months'],
            'description' => $data['description'] ?? '',
        ]);

        if ($package->exists) {
            return ApiResponse::sendResponse(200, 'Package Created Successfully', new PackageResource($package));
        }

        return ApiResponse::sendResponse(500, 'Package Creation Failed', []);
    }

    public function getPackages()
    {
        $packages = Package::all();
        if ($packages->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No Packages Found', []);
        }
        return ApiResponse::sendResponse(200, 'Packages Retrieved Successfully', PackageResource::collection($packages));
    }
}
