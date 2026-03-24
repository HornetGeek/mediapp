<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\AvailableTime;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class DoctorsController extends Controller
{
    //

    public function index()
    {
        $doctors = Doctors::with(['specialty', 'availableTimes'])->latest()->paginate(10);

        // dd($doctors);

        $specialties = Specialty::all();

        return view('dashboard.super_admin.doctors.index', compact('doctors', 'specialties'));
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required',
            // 'date.*' => 'required|date',
            // 'start_time.*' => 'required',
            // 'end_time.*' => 'required',
            // 'status' => 'required',
            'password' => 'required',
            'specialty_id' => 'required',
            'address_1' => 'required|string|max:255',
            // 'address_2' => 'nullable|string|max:255',
            
        ]);
        // dd($validated);

        if ($validated->fails()) {
            return redirect()->back()->withErrors($validated)->withInput();
        }

        DB::beginTransaction();
        try {
            $data = $validated->validated();
            $data['password'] = Hash::make($request->password);

            $doctor = Doctors::create($data);

            
            // foreach ($request->date as $index => $date) {
            //     DoctorAvailability::create([
            //         'doctors_id' => $doctor->id,
            //         'date' => $date,
            //         'start_time' => $request->start_time[$index],
            //         'end_time' => $request->end_time[$index],
            //         'status' => $request->status[$index],
            //     ]);
            // }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            flash()->addError('حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage());
            return redirect()->back();
        }

        flash()->addSuccess('تم إضافة البيانات بنجاح.');

        return redirect()->route('doctors.index');
    }

    public function update(Request $request, $id)
    {

        $doctor = Doctors::findOrFail($id);

        if (!$doctor) {
            flash()->addError('هذا الطبيب غير موجود.');
            return redirect()->route('doctors.index');
        }

        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required',
            'specialty_id' => 'required',
            'address_1' => 'string|max:255',
            'phone' => 'nullable',
            'password' => 'nullable',
            // 'date' => 'required|date',
            // 'start_time' => 'required|date_format:H:i',
            // 'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        if ($validated->fails()) {
            return redirect()->back()->withErrors($validated)->withInput();
        }

        DB::transaction(function () use ($validated, $doctor, $request) {
            $data = $validated->validated();

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            } else {
                $data['password'] = $doctor->password;
            }

            $doctor->update($data);

            // foreach ($doctor->availableTimes as $availableTime) {
            //     $availableTime->update([
            //         'date' => $request->date,
            //         'start_time' => $request->start_time,
            //         'end_time' => $request->end_time,
            //     ]);
            // }
        });


        flash()->addSuccess('تم تعديل البيانات بنجاح.');

        return redirect()->route('doctors.index');
    }

    public function destroy($id)
    {
        $doctor = Doctors::findOrFail($id);
        if (!$doctor) {
            flash()->addError('هذا الطبيب غير موجود.');
            return redirect()->route('doctors.index');
        }
        $doctor->delete();
        flash()->addSuccess('تم حذف البيانات بنجاح.');
        return redirect()->route('doctors.index');
    }
}
