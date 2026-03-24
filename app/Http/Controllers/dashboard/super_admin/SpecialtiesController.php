<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SpecialtiesController extends Controller
{
    //

    public function index()
    {
        $specialties = Specialty::all();
        return view('dashboard.super_admin.specialties.index', compact('specialties'));
    }


    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validated->fails()) {

            flash()->addError('حدث خطأ أثناء إضافة البيانات.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        Specialty::create($request->all());

        flash()->addSuccess('تم إضافة البيانات بنجاح.');

        return redirect()->route('specialties.index');
    }

    public function update(Request $request, $id)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validated->fails()) {

            flash()->addError('حدث خطأ أثناء تعديل البيانات.');
            return redirect()->back()->withErrors($validated)->withInput();
        }
        $specialty = Specialty::find($id);

        $specialty->update($request->all());

        flash()->addSuccess('تم تعديل البيانات بنجاح.');

        return redirect()->route('specialties.index');
    }

    public function destroy($id)
    {
        $specialty = Specialty::find($id);
        $specialty->delete();

        flash()->addSuccess('تم حذف البيانات بنجاح.');

        return redirect()->route('specialties.index');
    }
}
