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

    public function getDoctors(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $doctors = Doctors::with(['specialty', 'availableTimes'])->paginate($perPage);
        $pagination = $this->buildPaginationMeta($doctors);
        $items = ListDoctorsResource::collection($doctors->items());

        if ($doctors->total() === 0) {
            return ApiResponse::sendResponse(404, 'No Doctors Found', [], $pagination);
        }

        return ApiResponse::sendResponse(200, 'Doctors Retrieved Successfully', $items, $pagination);
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
        
        $representatives = $doctor->appointments()
            ->with('representative')
            ->get()
            ->pluck('representative')
            ->filter()
            ->unique('id');

        foreach ($representatives as $representative) {
            // dd($representative);
            if(!empty($representative->fcm_token)) {
                try {
                    $dedupeKey = sprintf(
                        'doctor_deleted:%d:to:rep:%d',
                        (int) $doctor->id,
                        (int) $representative->id
                    );

                    event(new SendNotificationEvent(
                        $representative,
                        'تم إلغاء الموعد',
                        'تم حذف الطبيب ' . $doctor->name . ' من قبل المشرف، وتم إلغاء جميع المواعيد الخاصة به.',
                        null,
                        [],
                        $dedupeKey
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

    private function buildPaginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}
