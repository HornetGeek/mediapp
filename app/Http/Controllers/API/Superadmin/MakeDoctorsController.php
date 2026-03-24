<?php

namespace App\Http\Controllers\API\SuperAdmin;

use App\Events\SendNotificationEvent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorsRequest;
use App\Http\Resources\ListDoctorsResource;
use App\Http\Resources\SpecialtiesResource;
use App\Models\Doctors;
use App\Models\Representative;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeDoctorsController extends Controller
{

    public function getDoctors()
    {
        $doctors = Doctors::with(['specialty', 'availableTimes'])->get();
        if ($doctors->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No Doctors Found', []);
        }
        return ApiResponse::sendResponse(200, 'Doctors Retrieved Successfully', ListDoctorsResource::collection($doctors));
    }

    public function createDoctor(DoctorsRequest $request)
    {
        $doctor = Doctors::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'address_1' => $request->address_1,
            // 'address_2' => $request->address_2,
            'specialty_id' => $request->specialty_id,
        ]);
        

        
        // if ($request->has('availabilities')) {
        //     foreach ($request->availabilities as $availability) {
        //         $doctor->availableTimes()->create([
        //             'date' => $availability['date'],
        //             'start_time' => $availability['start_time'],
        //             'end_time' => $availability['end_time'],
        //             'status' => $availability['status'],
        //         ]);
        //     }
        // }

        
        $data['token'] = $doctor->createToken('doctor-token', ['doctor'])->plainTextToken;
        $data['name'] = $doctor->name;
        $data['email'] = $doctor->email;

        return ApiResponse::sendResponse(201, 'Doctor Created Successfully', $data);
        
    }

    public function get_Speciality(Request $request)
    {
        // dd($doctor);
        $specialities = Specialty::all();

        if ($specialities) {
            return ApiResponse::sendResponse(200, 'Speciality Found', SpecialtiesResource::collection($specialities));
        }
        return ApiResponse::sendResponse(200, 'Speciality Not Found', []);
    }

    public function deleteDoctor($id)
    {
        $doctor = Doctors::findOrFail($id);
    
        if (!$doctor) {
            return ApiResponse::sendResponse(404, 'No Doctors Found', []);
        }
        
        foreach ($doctor->appointments as $appointment) {
            $representative = $appointment->representative;
            // dd($representative);
            if(!empty($representative->fcm_token)) {
                try {
                    event(new SendNotificationEvent(
                        $representative,
                        'تم إلغاء الموعد',
                        'تم حذف الطبيب ' . $doctor->name . ' من قبل المشرف، وتم إلغاء جميع المواعيد الخاصة به.'
                    ));
                } catch (\Exception $e) {
                    \Log::error('خطأ أثناء إرسال إشعار للمندوب: ' . $representative->id . ' - ' . $e->getMessage());
                }
            }


        }

        $doctor->appointments()->delete();
        $doctor->delete();
        
        return ApiResponse::sendResponse(200, 'Doctor Deleted Successfully', []);
    }
}
