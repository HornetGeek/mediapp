<?php

namespace App\Http\Controllers\API\representatives;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\RepCompanyCatalogResource;
use App\Models\RepCompanyCatalog;
use Illuminate\Http\Request;

class RepCompanyCatalogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $search = trim((string) $request->input('search', ''));

        $companies = RepCompanyCatalog::query()
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('normalized_name', 'like', '%' . RepCompanyCatalog::normalizeName($search) . '%');
            })
            ->orderByRaw('rank IS NULL, rank ASC')
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::sendResponse(
            200,
            'Representative company catalog retrieved successfully',
            RepCompanyCatalogResource::collection($companies->items()),
            [
                'current_page' => $companies->currentPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'last_page' => $companies->lastPage(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem(),
                'has_more_pages' => $companies->hasMorePages(),
            ]
        );
    }
}
