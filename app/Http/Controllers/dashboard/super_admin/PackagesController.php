<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    //

    public function index() {

        $packages = Package::latest()->paginate(10);

        return view('dashboard.super_admin.packages.index', compact('packages'));
    }

    public function store(Request $request) {
        
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'duration' => 'required|integer',
            'description' => 'nullable|string',
        ]);

        if ($validated->fails()) {

            flash()->addError('حدث خطأ أثناء إضافة الباقة.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        Package::create($request->all());

        flash()->addSuccess('تم إضافة الباقة بنجاح.');

        return redirect()->route('packages.index');
    }

    public function update(Request $request, $id) {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'duration' => 'required|integer',
            'description' => 'nullable|string',
        ]);

        if ($validated->fails()) {

            flash()->addError('حدث خطأ أثناء إضافة الباقة.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        $package = Package::findOrFail($id);
        $package->update($request->all());

        flash()->addSuccess('تم تحديث الباقة بنجاح.');

        return redirect()->route('packages.index');
    }

    public function destroy($id) {
        $package = Package::findOrFail($id);
        $package->delete();

        flash()->addSuccess('تم حذف الباقة بنجاح.');

        return redirect()->route('packages.index');
    }
    
}
