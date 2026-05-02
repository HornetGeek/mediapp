<?php

namespace App\Http\Controllers\API\Superadmin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\RepsResource;
use App\Models\Company;
use App\Models\RepCompanyCatalog;
use App\Models\Representative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RepresentativeApprovalController extends Controller
{
    public function pending(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $representatives = Representative::with(['company', 'companyCatalog', 'areas', 'lines'])
            ->where('registration_status', 'pending')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::sendResponse(
            200,
            'Pending representatives retrieved successfully',
            RepsResource::collection($representatives->items()),
            [
                'current_page' => $representatives->currentPage(),
                'per_page' => $representatives->perPage(),
                'total' => $representatives->total(),
                'last_page' => $representatives->lastPage(),
                'from' => $representatives->firstItem(),
                'to' => $representatives->lastItem(),
                'has_more_pages' => $representatives->hasMorePages(),
            ]
        );
    }

    public function approve($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => ['required_without:company_catalog_id', 'nullable', 'integer', 'exists:companies,id'],
            'company_catalog_id' => ['required_without:company_id', 'nullable', 'integer', 'exists:rep_company_catalogs,id'],
            'daily_visits_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $data = $validator->validated();
        if (!empty($data['company_id']) && !empty($data['company_catalog_id'])) {
            return ApiResponse::sendResponse(422, 'Choose either an existing company or a catalog company, not both', []);
        }

        $representative = Representative::findOrFail($id);

        if (!empty($data['company_id'])) {
            $company = Company::findOrFail($data['company_id']);
            $representative->company_id = $company->id;
            $representative->company_catalog_id = null;
            $representative->daily_visits_limit = $data['daily_visits_limit'] ?? $company->visits_per_day;
        } else {
            $catalog = RepCompanyCatalog::findOrFail($data['company_catalog_id']);
            $representative->company_id = null;
            $representative->company_catalog_id = $catalog->id;
            $representative->daily_visits_limit = $data['daily_visits_limit'] ?? config('reps.self_registered_daily_visits_limit');
        }

        $representative->requested_company_name = null;
        $representative->registration_status = 'active';
        $representative->save();

        return ApiResponse::sendResponse(
            200,
            'Representative approved successfully',
            new RepsResource($representative->load(['company', 'companyCatalog', 'areas', 'lines']))
        );
    }

    public function reject($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'registration_status' => ['nullable', Rule::in(['rejected'])],
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $representative = Representative::findOrFail($id);
        $representative->registration_status = 'rejected';
        $representative->save();

        return ApiResponse::sendResponse(
            200,
            'Representative rejected successfully',
            new RepsResource($representative->load(['company', 'companyCatalog', 'areas', 'lines']))
        );
    }
}
