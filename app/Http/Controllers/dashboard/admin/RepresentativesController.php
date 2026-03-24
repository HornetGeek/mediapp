<?php

namespace App\Http\Controllers\dashboard\admin;

use App\Http\Controllers\Controller;
use App\Models\Representative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RepresentativesController extends Controller
{
    //

    public function index()
    {
        // Fetch all representatives from the database
        $representatives = Representative::where('status', 'active')->get();

        // Return the view with the representatives data
        return view('dashboard.admin.representatives.index', compact('representatives'));
    }

    public function store(Request $request) {

        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء اضافة البيانات.');
            return redirect()->back()->withErrors($validated)->withInput();
        }
        $user = auth()->user();
        $companyId = $user->company_id;
        
        $representative = new Representative();
        $representative->name = $request->name;
        $representative->company_id = $companyId;
        $representative->email = $request->email;
        $representative->phone = $request->phone;
        $representative->password = Hash::make($request->password);
        $representative->status = $request->status;
        $representative->save();
        flash()->addSuccess('تم اضافة البيانات بنجاح.');
        return redirect()->back();
    }

    public function destroy($id) {
        $representative = Representative::find($id);
        if ($representative) {
            $representative->delete();
            flash()->addSuccess('تم حذف البيانات بنجاح.');
        } else {
            flash()->addError('حدث خطأ أثناء حذف البيانات.');
        }
        return redirect()->back();
    }
    
}
