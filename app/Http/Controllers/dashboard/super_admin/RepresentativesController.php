<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Representative;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepresentativesController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim((string) $request->query('search', ''));

        $representatives = Representative::with(['company', 'companyCatalog', 'areas', 'lines'])
            ->when($search !== '', function ($query) use ($search) {
                $term = '%' . $search . '%';

                $query->where(function ($query) use ($search, $term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('requested_company_name', 'like', $term)
                        ->orWhere('requested_line_name', 'like', $term)
                        ->orWhere('requested_area_names', 'like', $term)
                        ->orWhere('registration_status', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhere('created_at', 'like', $term)
                        ->orWhereHas('company', fn ($company) => $company->where('name', 'like', $term))
                        ->orWhereHas('companyCatalog', fn ($company) => $company->where('name', 'like', $term))
                        ->orWhereHas('lines', fn ($line) => $line->where('name', 'like', $term))
                        ->orWhereHas('areas', fn ($area) => $area->where('name', 'like', $term));

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.super_admin.representatives.index', compact('representatives', 'search'));
    }
}
