<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Package;
use App\Services\SubscriptionPlanService;
use App\Traits\FilterTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CompaniesController extends Controller
{
    use FilterTrait;
    //
    public function index()
    {
        $companies = Company::latest()->paginate(10);

        $packages = Package::all();

        return view('dashboard.super_admin.companies.index', compact('companies', 'packages'));
    }

    public function store(Request $request, SubscriptionPlanService $subscriptionPlanService)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'package_id' => 'required|integer',
            'email' => 'required|email|unique:companies,email',
            'password' => 'required|min:8',

        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء اضافة البيانات.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        DB::beginTransaction();

        try {
            $package = Package::findOrFail($request->package_id);
            $subscriptionStart = now();

            $company = Company::create([
                'name' => $request->name,
                'package_id' => $request->package_id,
                'subscription_start' => $subscriptionStart,
                'subscription_end' => $subscriptionPlanService->calculateSubscriptionEndDate($subscriptionStart, $package),
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'visits_per_day' => $package->visits_per_day,
                'num_of_reps' => $package->num_of_reps,
                'status' => $request->status ?? 'active',

            ]);

            DB::commit();

            flash()->addSuccess('تم إضافة البيانات بنجاح.');
            return redirect()->route('companies.index');
        } catch (\Exception $e) {
            DB::rollBack();
            flash()->addError('حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function update(Request $request, $id, SubscriptionPlanService $subscriptionPlanService)
    {
        $company = Company::findOrFail($id);

        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'package_id' => 'required|integer',
            'email' => 'required|email|unique:companies,email,' . $company->id,
            'password' => 'nullable|min:8',
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء اضافة البيانات.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        DB::transaction(function () use ($request, $company, $subscriptionPlanService) {
            $package = Package::find($request->package_id);

            // استخدام تاريخ الاشتراك من الفورم إذا حدد المستخدم، أو القديم أو الآن
            $subscriptionStart = $request->subscription_start
                ? Carbon::parse($request->subscription_start)
                : ($company->subscription_start ? Carbon::parse($company->subscription_start) : now());

            // إذا غيرت الباقة، نضيف مدة الباقة لتحديد انتهاء الاشتراك
            $subscriptionEnd = $package
                ? $subscriptionPlanService->calculateSubscriptionEndDate($subscriptionStart, $package)
                : ($company->subscription_end ?? $subscriptionStart->copy()->addDays(30));

            // الحالة تعتمد على نهاية الاشتراك
            $status = now()->lte($subscriptionEnd) ? 'active' : 'inactive';

            $company->update([
                'name' => $request->name,
                'package_id' => $request->package_id,
                'phone' => $request->phone,
                'email' => $request->email,
                'password' => $request->filled('password') ? Hash::make($request->password) : $company->password,
                'visits_per_day' => $request->visits_per_day,
                'num_of_reps' => $request->num_of_reps,
                'subscription_start' => $subscriptionStart,
                'subscription_end' => $subscriptionEnd,
                'status' => $status,
            ]);
        });

        flash()->addSuccess('تم تحديث البيانات بنجاح.');
        return redirect()->route('companies.index');
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);
        $company->delete();

        flash()->addSuccess('تم حذف البيانات بنجاح.');
        return redirect()->route('companies.index');
    }

    public function getCompaniesByStatus(Request $request)
    {
        $search = $request->query('query');
        $companies = $this->filterByStatus(Company::class, $search);
        // dd($search); 
        return response()->json($companies, 200);
    }
}
