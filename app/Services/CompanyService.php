<?php

namespace App\Services;

use App\Helpers\ApiResponse;
use App\Http\Resources\CompanyResource;
use App\Models\Company;

class CompanyService
{
    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService
    ) {
    }

    /**
     * Create a new company.
     *
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCompany($data, $package)
    {
        $subscriptionStart = now();

        $company = new Company();
        $company->name = $data['name'];
        $company->email = $data['email'];
        $company->password = bcrypt($data['password']);
        $company->visits_per_day = $data['visits_per_day'];
        $company->num_of_reps = $data['num_of_reps'];
        $company->phone = $data['phone'];
        $company->subscription_start = $subscriptionStart;
        $company->subscription_end = $this->subscriptionPlanService->calculateSubscriptionEndDate($subscriptionStart, $package);
        $company->package_id = $data['package_id'];
        $company->status = 'active';

        if ($company->save()) {
            return ApiResponse::sendResponse(200, 'Company Created Successfully', new CompanyResource($company));
        } else {
            return ApiResponse::sendResponse(500, 'Company Creation Failed', []);
        }
    }

    public function getCompanies()
    {
        $companies = Company::with('package')->get();
        return ApiResponse::sendResponse(200, 'Companies Retrieved Successfully', CompanyResource::collection($companies));
    }

    public function changeStatus($company_id, $status)
    {
        $company = Company::findOrFail($company_id);

        if (!in_array($status, ['active', 'inactive'])) {
            return ApiResponse::sendResponse(400, 'Invalid Status', []);
        }
        // 

        $company->status = $status;
        if ($company->save()) {
            return ApiResponse::sendResponse(200, 'Company Status Updated Successfully', new CompanyResource($company));
        } else {
            return ApiResponse::sendResponse(500, 'Company Status Update Failed', []);
        }

    }

}
