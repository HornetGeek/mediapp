<?php
namespace App\Services;

use App\Events\SendNotificationEvent;
use App\Models\Representative;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\RepsResource;
use App\Helpers\ApiResponse;
use App\Models\Appointment;
use Illuminate\Support\Arr;


class RepresentativeService
{
    public function create(array $data)
    {
        $company = Auth::user();
        $countReps = Representative::where('company_id', $company->id)->count();
        $maxReps = $company->num_of_reps;

        if ($countReps >= $maxReps) {
            return ApiResponse::sendResponse(403, 'You have reached the maximum number of representatives allowed for your company', []);
        }

        $representative = new Representative();
        $representative->name = $data['name'];
        $representative->email = $data['email'];
        $representative->password = Hash::make($data['password']);
        $representative->phone = $data['phone'];
        $representative->status = $data['status'] ?? 'active';
        $representative->company_id = $company->id;
        $representative->save();

        $representative->areas()->sync($data['area_ids']);
        if (isset($data['line_ids'])) {
            $representative->lines()->sync($data['line_ids']);
        }


        return ApiResponse::sendResponse(200, 'Representative created successfully', new RepsResource($representative->load(['lines', 'areas', 'company'])));
    }

    public function edit(array $data)
    {
        $representative = Representative::find($data['rep_id']);

        // $representative = Representative::where('id', $data['rep_id'])
        //     ->where('company_id', Auth::id())
        //     ->first();

        // if (!$representative) {
        //     return ApiResponse::sendResponse(404, 'Representative not found or unauthorized', []);
        // }
        if (!$representative) {
            return ApiResponse::sendResponse(404, 'Representative not found', []);
        }

        if ($representative->company_id !== Auth::user()->id) {
            return ApiResponse::sendResponse(403, 'Unauthorized action', []);
        }

        $representative->fill(Arr::only($data, ['name', 'email', 'phone']));
        if (isset($data['password'])) {
            $representative->password = Hash::make($data['password']);
        }
        $representative->save();

        if (isset($data['area_ids'])) {
            $representative->areas()->sync($data['area_ids']);
        }
        if (isset($data['line_ids'])) {
            $representative->lines()->sync($data['line_ids']);
        }

        return ApiResponse::sendResponse(200, 'Representative updated successfully', new RepsResource($representative->load(['lines', 'areas', 'company'])));
    }

    public function delete($id)
    {
        $representative = Representative::find($id);

        if (!$representative) {
            return ApiResponse::sendResponse(404, 'Representative not found', []);
        }

        if ($representative->company_id !== Auth::user()->id) {
            return ApiResponse::sendResponse(403, 'Unauthorized action', []);
        }

        foreach ($representative->appointments as $appointment) {
            $doctor = $appointment->doctor;
            if (!empty($doctor->fcm_token)) {
                try {
                    event(new SendNotificationEvent(
                        $doctor,
                        'تم إلغاء الموعد',
                        'تم حذف المندوب ' . $representative->name . ' من قبل الشركة، وتم إلغاء جميع المواعيد الخاصة به.'
                    ));
                } catch (\Exception $e) {
                    \Log::error('خطأ أثناء إرسال إشعار للطبيب: ' . $doctor->id . ' - ' . $e->getMessage());
                }

            }
        }

        $representative->appointments()->delete();

        $representative->delete();

        return ApiResponse::sendResponse(200, 'Representative deleted successfully', []);
    }
}