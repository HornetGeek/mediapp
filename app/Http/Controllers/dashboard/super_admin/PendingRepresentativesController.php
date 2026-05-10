<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\RepCompanyCatalog;
use App\Models\Representative;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PendingRepresentativesController extends Controller
{
    public function index(): View
    {
        $representatives = Representative::with(['companyCatalog'])
            ->where('registration_status', 'pending')
            ->latest()
            ->paginate(20);

        return view('dashboard.super_admin.pending_representatives.index', compact('representatives'));
    }

    public function approve(int $id): RedirectResponse
    {
        $representative = Representative::where('registration_status', 'pending')->findOrFail($id);
        $requestedCompanyName = trim((string) $representative->requested_company_name);

        if ($requestedCompanyName === '') {
            flash()->addError('Cannot approve this representative because the requested company name is missing.');

            return redirect()->back();
        }

        $normalizedName = RepCompanyCatalog::normalizeName($requestedCompanyName);
        $catalog = RepCompanyCatalog::firstOrCreate(
            ['normalized_name' => $normalizedName],
            [
                'name' => $requestedCompanyName,
                'status' => 'active',
            ]
        );

        if ($catalog->status !== 'active') {
            $catalog->status = 'active';
            $catalog->save();
        }

        $representative->company_id = null;
        $representative->company_catalog_id = $catalog->id;
        $representative->requested_company_name = null;
        $representative->registration_status = 'active';
        $representative->daily_visits_limit = $representative->daily_visits_limit
            ?? config('reps.self_registered_daily_visits_limit');
        $representative->save();

        flash()->addSuccess('Representative approved successfully.');

        return redirect()->route('pending-representatives.index');
    }

    public function reject(int $id): RedirectResponse
    {
        $representative = Representative::where('registration_status', 'pending')->findOrFail($id);
        $representative->registration_status = 'rejected';
        $representative->save();

        flash()->addSuccess('Representative rejected successfully.');

        return redirect()->route('pending-representatives.index');
    }
}
