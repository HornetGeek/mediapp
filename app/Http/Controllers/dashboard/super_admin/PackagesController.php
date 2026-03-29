<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    public function index()
    {
        $packages = Package::latest()->paginate(10);

        return view('dashboard.super_admin.packages.index', compact('packages'));
    }

    public function store(Request $request, SubscriptionPlanService $subscriptionPlanService)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'plan_type' => 'required|in:quarterly,semi_annual,annual',
            'description' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء إضافة الباقة.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        $validatedData = $validated->validated();
        $normalizedPlan = $subscriptionPlanService->normalizePackageAttributes($validatedData);

        Package::create([
            'name' => $validatedData['name'],
            'price' => $validatedData['price'],
            'duration' => $normalizedPlan['duration'],
            'plan_type' => $normalizedPlan['plan_type'],
            'billing_months' => $normalizedPlan['billing_months'],
            'description' => $validatedData['description'] ?? null,
        ]);

        flash()->addSuccess('تم إضافة الباقة بنجاح.');

        return redirect()->route('packages.index');
    }

    public function update(Request $request, $id, SubscriptionPlanService $subscriptionPlanService)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'plan_type' => 'required|in:quarterly,semi_annual,annual',
            'description' => 'nullable|string',
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء إضافة الباقة.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        $validatedData = $validated->validated();
        $normalizedPlan = $subscriptionPlanService->normalizePackageAttributes($validatedData);

        $package = Package::findOrFail($id);
        $package->update([
            'name' => $validatedData['name'],
            'price' => $validatedData['price'],
            'duration' => $normalizedPlan['duration'],
            'plan_type' => $normalizedPlan['plan_type'],
            'billing_months' => $normalizedPlan['billing_months'],
            'description' => $validatedData['description'] ?? null,
        ]);

        flash()->addSuccess('تم تحديث الباقة بنجاح.');

        return redirect()->route('packages.index');
    }

    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        flash()->addSuccess('تم حذف الباقة بنجاح.');

        return redirect()->route('packages.index');
    }
}
