<?php 

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Http\Resources\PackageResource;
use App\Models\Package;

class PackageService {
    

    public function createPackage($data) 
    {
        $package = new Package();
        $package->name = $data['name'];
        $package->duration = $data['duration'];
        $package->price = $data['price'];
        $package->description = $request->description ?? '';

        if ($package->save()) {
            return ApiResponse::sendResponse(200, 'Package Created Successfully', new PackageResource($package));
        } else {
            return ApiResponse::sendResponse(500, 'Package Creation Failed', []);
        }
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