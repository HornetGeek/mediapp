<?php

namespace App\Http\Controllers\API\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\CompanyRequest;
use App\Http\Requests\PackageRequest;
use Illuminate\Http\Request;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\PackageResource;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\PackageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeCompanyController extends Controller
{
    public function createCompany (CompanyRequest $request, CompanyService $companyService) 
    {
        $package = Package::findOrFail($request->package_id);
        
        return $companyService->createCompany($request->validated(), $package);
    }

    public function getCompanies() 
    {
        $companies = Company::with('package')->get();
        return ApiResponse::sendResponse(200, 'Companies Retrieved Successfully', CompanyResource::collection($companies));
    }

    public function changeCompanyStatus($company_id, Request $request, CompanyService $companyService) 
    {    
        return $companyService->changeStatus($company_id, $request->input('status'));
    }

    public function deleteCompany($company_id) 
    {
        $company = Company::findOrFail($company_id);
        if ($company->delete()) {
            return ApiResponse::sendResponse(200, 'Company Deleted Successfully', []);
        } else {
            return ApiResponse::sendResponse(500, 'Company Deletion Failed', []);
        }
    }

    public function createPackage(PackageRequest $request, PackageService $packageService) 
    {
        return $packageService->createPackage($request->validated());
    }

    public function getPackages() 
    {
        return (new PackageService())->getPackages();
    }

    public function deletePackage($package_id) 
    {
        $package = Package::findOrFail($package_id);
        if ($package->delete()) {
            return ApiResponse::sendResponse(200, 'Package Deleted Successfully', []);
        } else {
            return ApiResponse::sendResponse(500, 'Package Deletion Failed', []);
        }
    }

    public function getActiveCompanies() 
    {
        $activeCompanies = Company::where('status', 'active')->with('package')->get();
        return ApiResponse::sendResponse(200, 'Active Companies Retrieved Successfully', CompanyResource::collection($activeCompanies));
    }

    public function getInactiveCompanies() 
    {
        $inactiveCompanies = Company::where('status', 'inactive')->with('package')->get();
        return ApiResponse::sendResponse(200, 'Inactive Companies Retrieved Successfully', CompanyResource::collection($inactiveCompanies));
    }

    
}
