<?php

namespace App\Http\Controllers\API\doctors;

use App\Events\SendNotificationEvent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AvailableTimeResource;
use App\Http\Resources\BlockedCompanyResource;
use App\Http\Resources\BlockedRepsResource;
use App\Http\Resources\BlockSearchResource;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\DoctorAppointmentsResource;
use App\Http\Resources\FiltersResource;
use App\Http\Resources\NotificationsResource;
use App\Http\Resources\SpecialtiesResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorBlock;
use App\Models\Doctors;
use App\Models\FeedbackEmail;
use App\Models\Notification;
use App\Models\Representative;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentStatusRefreshService;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PhpParser\Comment\Doc;

class DoctorsController extends Controller
{
    //

    public function availableTimes(Request $request)
    {

        $doctor = $request->user();
        // dd($doctor);
        $doctor->load([
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            }
        ]);
        if ($doctor) {
            return ApiResponse::sendResponse(200, 'Doctor Found With Available times ', new DoctorResource($doctor));
        }
        return ApiResponse::sendResponse(200, 'Doctor Not Found', []);
    }

    public function get_Speciality()
    {
        // dd($doctor);
        $specialities = Specialty::all();

        if ($specialities) {
            return ApiResponse::sendResponse(200, 'Speciality Found', SpecialtiesResource::collection($specialities));
        }
        return ApiResponse::sendResponse(200, 'Speciality Not Found', []);
    }


    public function getDoctorProfile(Request $request)
    {
        $doctor = $request->user();

        if (!$doctor) {
            return ApiResponse::sendResponse(404, 'Doctor not found');
        }

        $doctor->load(
            [
                'availableTimes' => function ($query) {
                    $query->select('id', 'doctors_id', 'date', 'start_time', 'end_time', 'status');
                }
            ]
        );
        // dd($doctor);

        return ApiResponse::sendResponse(200, 'Doctor retrieved successfully', new DoctorResource($doctor));
    }

    public function updateDoctorProfile(Request $request)
    {
        $doctor = $request->user();

        $validated = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:doctors,email,' . $doctor->id],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:doctors,phone,' . $doctor->id],
            'specialty_id' => ['sometimes', 'exists:specialties,id'],
        ]);
        // $request->validate([
        //     'name' => ['sometimes', 'string', 'max:255'],
        //     'email' => ['sometimes', 'email', 'max:255', 'unique:doctors,email,' . $doctor->id],
        //     'phone' => ['sometimes', 'string', 'max:20', 'unique:doctors,phone,' . $doctor->id],
        //     'specialty_id' => ['sometimes', 'exists:specialties,id'],
        // ]);

        if ($validated->fails()) {
            return ApiResponse::sendResponse(422, $validated->messages()->first(), []);
        }

        $doctor->update($request->only(['name', 'email', 'phone', 'specialty_id']));
        $doctor->save();

        return ApiResponse::sendResponse(200, 'Doctor profile updated successfully', new DoctorResource($doctor));
    }

    public function editStatus(Request $request)
    {
        $doctor = $request->user();

        $validated = Validator::make($request->all(), [
            'status' => ['sometimes', 'in:active,busy'],
        ]);
        if ($validated->fails()) {
            return ApiResponse::sendResponse(422, $validated->messages()->first(), []);
        }

        $doctor->update($request->only(['status', 'from_date', 'to_date']));
        $doctor->save();
        if($request->status != 'busy'){
            $doctor->from_date = null;
            $doctor->to_date = null;
            $doctor->status = 'active';
            $doctor->save();
            
            return ApiResponse::sendResponse(200, 'Doctor status updated successfully', new DoctorResource($doctor));
        }
        // check if status is busy then cancel all pending appointments
        if ($doctor->status === 'busy') {
            $appointments = Appointment::with('representative')
                ->where('doctors_id', $doctor->id)
                ->where('status', 'pending')
                ->whereBetween('date', [$doctor->from_date, $doctor->to_date])
                ->get();

            Appointment::whereIn('id', $appointments->pluck('id'))
                ->update(['status' => 'cancelled', 'cancelled_by' => 'Dr.' . $doctor->name]);
        }
        $dateBusyFrom = Carbon::parse($doctor->from_date)->format('Y-m-d h:i a');
        $dateBusyTo = Carbon::parse($doctor->to_date)->format('Y-m-d h:i a');

        $representatives = $appointments
            ->pluck('representative')
            ->unique('id');
        foreach ($representatives as $rep) {
            event(new SendNotificationEvent(
                $rep,
                'Visit Cancelled Due to Doctor’s Custom Busy Period',
                'Dr. ' . $doctor->name .
                ' is unavailable from ' . $dateBusyFrom .
                ' to ' . $dateBusyTo .
                '. Your visit has been cancelled.',
                'reps'
            ));
        }

        return ApiResponse::sendResponse(200, 'Doctor status updated successfully', new DoctorResource($doctor));
    }

    public function saveAvailableTimes(Request $request)
    {
        $doctor = $request->user();

        // $request->validate([
        //     'availabilities' => ['array'],
        //     'availabilities.*.date' => ['required', 'string'], // "Monday"
        //     'availabilities.*.start_time' => ['required', 'string'],
        //     'availabilities.*.end_time' => ['required', 'string'],
        //     'availabilities.*.status' => ['nullable', 'string'],
        // ]);

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'string'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422,$validator->errors()->first(),[]);
        }
        $validated = $validator->validated();

        try {
            // convert time from 12h to 24h format
            $startTime = Carbon::createFromFormat('h:i A', $validated['start_time'])->format('H:i:s');
            $endTime = Carbon::createFromFormat('h:i A', $validated['end_time'])->format('H:i:s');
        } catch (\Exception $e) {
            return ApiResponse::sendResponse(422, 'Invalid time format, please use hh:mm AM/PM');
        }
        $conflict = $doctor->availableTimes()
            ->where('date', $validated['date'])
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
            ->exists();

        if ($conflict) {
            return ApiResponse::sendResponse(422, "You already have an availability for {$validated['date']} at the same time");
        }


        $created = $doctor->availableTimes()->create([
            'date' => $validated['date'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $validated['status'] ?? 'available',
        ]);
        return ApiResponse::sendResponse(200, 'Availabilities saved successfully', DoctorResource::make($doctor->load('availableTimes')));



        //     $created = [];

        //     foreach ($request->availabilities as $availability) {
        //         // convert time from 12h to 24h format
        //         try {
        //             $startTime = Carbon::createFromFormat('h:i A', $availability['start_time'])->format('H:i:s');
        //             $endTime   = Carbon::createFromFormat('h:i A', $availability['end_time'])->format('H:i:s');
        //         } catch (\Exception $e) {
        //             return ApiResponse::sendResponse(422, 'Invalid time format, please use hh:mm AM/PM');
        //         }

        //         // تحقق من وجود تداخل
        //         // $conflict = $doctor->availableTimes()
        //         //     ->where('date', $availability['date'])
        //         //     ->where(function ($query) use ($startTime, $endTime) {
        //         //         if ($startTime < $endTime) {
        //         //             // حالة طبيعية: بنفس اليوم
        //         //             $query->where('start_time', '<', $endTime)
        //         //                 ->where('end_time', '>', $startTime);
        //         //         } else {
        //         //             // حالة الموعد ممتد لليوم التالي
        //         //             $query->where(function ($q) use ($startTime, $endTime) {
        //         //                 $q->where('start_time', '>=', $startTime) // بعد البداية
        //         //                     ->orWhere('end_time', '<=', $endTime);  // أو قبل النهاية
        //         //             });
        //         //         }
        //         //     })
        //         //     ->exists();

        //         // if ($conflict) {
        //         //     return ApiResponse::sendResponse(422, 'This time conflicts with an existing availability');
        //         // }

        //         $conflict = $doctor->availableTimes()
        //             ->where('date', $availability['date'])
        //             ->where('start_time', $startTime)
        //             ->where('end_time', $endTime)
        //             ->exists();

        //         if ($conflict) {
        //             return ApiResponse::sendResponse(422, "You already have an availability for {$availability['date']} at the same time");
        //         }


        //         $created[] = $doctor->availableTimes()->create([
        //             'date'       => $availability['date'], // (مثلاً "Monday")
        //             'start_time' => $startTime,
        //             'end_time'   => $endTime,
        //             'status'     => $availability['status'] ?? 'available',
        //         ]);
        //     }

        //     return ApiResponse::sendResponse(200, 'Availabilities saved successfully', DoctorResource::make($doctor->load('availableTimes')));
    }

    // public function copyLastMonthTimes(Request $request)
    // {
    //     $doctor = $request->user();

    //     $lastMonth = now()->subMonth()->month;
    //     $lastYear = now()->subMonth()->year;

    //     $lastMonthAvailabilities = $doctor->availableTimes()
    //         ->whereMonth('date', $lastMonth)
    //         ->whereYear('date', $lastYear)
    //         ->get();

    //     if ($lastMonthAvailabilities->isEmpty()) {
    //         return ApiResponse::sendResponse(404, 'No availabilities found for last month', []);
    //     }

    //     // $newAvailabilities = [];
    //     $updatedAvailabilities = [];
    //     foreach ($lastMonthAvailabilities as $availability) {
    //         // غير السنة والشهر للتاريخ الجديد
    //         $newDate = \Carbon\Carbon::parse($availability->date)->addMonth();

    //         $availability->update([
    //             'date' => $newDate->toDateString(),
    //             'start_time' => $availability->start_time,
    //             'end_time' => $availability->end_time,
    //             'status' => $availability->status,
    //         ]);
    //         $updatedAvailabilities[] = $availability;
    //         // $newAvailabilities[] = $doctor->availableTimes()->create([
    //         //     'date'       => $newDate->toDateString(),
    //         //     'start_time' => $availability->start_time,
    //         //     'end_time'   => $availability->end_time,
    //         //     'status'     => $availability->status,
    //         // ]);
    //     }

    //     return ApiResponse::sendResponse(200, 'Availabilities copied from last month', AvailableTimeResource::collection($updatedAvailabilities));
    // }

    public function getDoctorAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:cancelled,confirmed,pending,left,suspended'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'status' => 'Status',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $doctor = $this->refreshDoctorAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor', 'company'])
            ->where('doctors_id', $doctor)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = DoctorAppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Appointments Not Found', [], $pagination);
    }

    public function cancellationAppointment($book_id, FirebaseNotificationService $service)
    {
        $doctor = auth()->user();

        $appointment = Appointment::where('id', $book_id)
            ->where('doctors_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        if ($appointment->status === 'cancelled') {
            return ApiResponse::sendResponse(400, 'Appointment is already cancelled', []);
        }

        $appointment->update(['status' => 'cancelled', 'cancelled_by' => 'Dr.' . $doctor->name]);

        // $notify = Notification::create([
        //     'user_id' => $doctor->id,
        //     'title' => 'تم إلغاء الموعد',
        //     'body' => $doctor->name .'تم إلغاء الموعد من قبل الدكتور',
        // ]);

        $reps = $appointment->representative;
        // dd($reps);
        // $service->sendNotification($reps->fcm_token, $notify->title, $notify->body);
        event(new SendNotificationEvent($reps, 'Visit Cancelled by Doctor', 'Your visit with Dr.' . $doctor->name . ' has been cancelled', 'reps'));

        return ApiResponse::sendResponse(200, 'Appointment cancelled successfully', new DoctorAppointmentsResource($appointment));
    }

    public function filterAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $doctor = $this->refreshDoctorAppointments($statusRefresh);


        $filters = $request->only(['name']);

        $serached = Appointment::with(['representative', 'company'])
            ->where('doctors_id', $doctor)
            ->filter($filters)
            ->get();
        // dd($serached);

        if ($serached->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No Appointments found', []);
        }
        return ApiResponse::sendResponse(200, 'Appointments fetched successfully', FiltersResource::collection($serached));
    }

    public function deleteAppointment($book_id)
    {
        $doctor = auth()->user();

        $appointment = Appointment::where('id', $book_id)
            ->where('doctors_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        $appointment->delete();

        return ApiResponse::sendResponse(200, 'Appointment deleted successfully', []);
    }

    public function getNotifications()
    {
        $doctor = auth()->user();
        $notifications = $doctor->notifications()
            ->orderBy('created_at', 'desc')
            ->get();
        
        if( $notifications->isEmpty() ) {
            return ApiResponse::sendResponse(200, 'No notifications found', []);
        }
        
        return ApiResponse::sendResponse(200, 'Notifications fetched successfully', NotificationsResource::collection($notifications));
    }

    public function markNotificationAsRead($notification_id)
    {
        $doctor = auth()->user();

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->update(['is_read' => true]);

        return ApiResponse::sendResponse(200, 'Notification marked as read successfully', new NotificationsResource($notification));
    }

    public function markAllNotificationsAsRead()
    {
        $doctor = auth()->user();

        $updatedCount = Notification::where('notifiable_id', $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResponse::sendResponse(200, 'All unread notifications marked as read successfully', [
            'updated_count' => $updatedCount,
        ]);
    }

    public function deleteNotification($notification_id)
    {
        $doctor = auth()->user();

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $doctor->id)
            ->where('notifiable_type', Doctors::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->delete();

        return ApiResponse::sendResponse(200, 'Notification deleted successfully', []);
    }

    public function clearAllNotifications(Request $request)
    {
        $doctor = auth()->user();
        // dd(auth()->user());

        $doctor->notifications()->delete();

        return ApiResponse::sendResponse(200, 'All notifications cleared successfully', []);
    }



    public function blockRepresentative(Request $request, $repId)
    {
        $doctor = $request->user();

        DoctorBlock::firstOrCreate([
            'doctors_id' => $doctor->id,
            'blockable_id' => $repId,
            'blockable_type' => Representative::class,
        ]);

        $appointment = Appointment::where('doctors_id', $doctor->id)
            ->where('representative_id', $repId)
            ->pending()
            ->get();

        Appointment::whereIn('id', $appointment->pluck('id'))->delete();

        $representatives = $appointment
            ->pluck('representative')
            ->unique('id');
        foreach ($representatives as $reps) {
            event(new SendNotificationEvent($reps, 'Visit Cancelled Due to Doctor Blocking the Rep', 'You’ve been blocked by Dr. ' . $doctor->name . ' Your visit has been cancelled.', 'reps'));
        }


        return ApiResponse::sendResponse(200, 'blocked representative successfully', []);
    }


    public function blockCompany(Request $request, $companyId)
    {
        $doctor = $request->user();

        if (Company::where('id', $companyId)->exists()) {
            DoctorBlock::firstOrCreate([
                'doctors_id' => $doctor->id,
                'blockable_id' => $companyId,
                'blockable_type' => Company::class,
            ]);
            $companyName = Company::find($companyId);
            $appointment = Appointment::with('representative')
                ->where('doctors_id', $doctor->id)
                ->where('company_id', $companyId)
                ->pending()
                ->get();

            Appointment::whereIn('id', $appointment->pluck('id'))->delete();

            $representatives = $appointment
                ->pluck('representative')
                ->unique('id');
            foreach ($representatives as $reps) {
                DoctorBlock::firstOrCreate([
                    'doctors_id' => $doctor->id,
                    'blockable_id' => $reps->id,
                    'blockable_type' => Representative::class,
                ]);

                event(new SendNotificationEvent($reps, 'Visit Cancelled Due to Doctor Blocking the Company', 'Dr. ' . $doctor->name . ' has blocked ' . $companyName->name . '. All your visits have been cancelled.', 'reps'));
            }

            return ApiResponse::sendResponse(200, 'blocked company successfully', []);
        } else {
            return ApiResponse::sendResponse(200, 'Not Found company', []);
        }

    }

    public function unblockCompany(Request $request, $companyId)
    {
        $doctor = $request->user();
        $company = Company::findOrFail($companyId);

        $companyBlock = DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_id', $companyId)
            ->where('blockable_type', Company::class);

        if (!$companyBlock->exists()) {
            return ApiResponse::sendResponse(404, 'Block record not found', []);
        }

        $companyBlock->delete();

        // get blocked reps of this company
        $blockedRepIds = DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_type', Representative::class)
            ->whereIn('blockable_id', function ($q) use ($companyId) {
                $q->select('id')
                    ->from('representatives')
                    ->where('company_id', $companyId);
            })
            ->pluck('blockable_id');

        $representatives = Representative::whereIn('id', $blockedRepIds)->get();

        // unblock reps
        DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_type', Representative::class)
            ->whereIn('blockable_id', $blockedRepIds)
            ->delete();

        // notify
        foreach ($representatives as $rep) {
            event(new SendNotificationEvent(
                $rep,
                'Company Unblocked',
                'Dr. ' . $doctor->name .
                ' has unblocked ' . $company->name .
                '. You can now book visits.',
                'reps'
            ));
        }

        return ApiResponse::sendResponse(
            200,
            'Unblocked company and its representatives successfully',
            []
        );
    }

    public function unblockRepresentative(Request $request, $repId)
    {
        $doctor = $request->user();

        $block = DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_id', $repId)
            ->where('blockable_type', Representative::class)
            ->first();

        $reps = Representative::where('id', $repId)->first();
        $getCompany = Company::where('id', $reps->company_id)->first();
        
        if ($getCompany) {
            $companyBlock = DoctorBlock::where('doctors_id', $doctor->id)
                ->where('blockable_id', $getCompany->id)
                ->where('blockable_type', Company::class)
                ->first();

            if ($companyBlock) {
                return ApiResponse::sendResponse(422, 'Cannot unblock representative while their company is blocked', []);
            }
        }

        if ($block) {
            $block->delete();
            event(new SendNotificationEvent($reps, 'Rep Unblock Notification', 'The block has been removed by Dr. ' . $doctor->name . ' You can now book visits.', 'reps'));
            return ApiResponse::sendResponse(200, 'Unblocked representative successfully', []);
        }

        return ApiResponse::sendResponse(404, 'Block record not found', []);
    }

    public function getBlockedCompanies(Request $request)
    {
        $doctor = $request->user();

        $blockedCompanies = DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_type', Company::class)
            ->with('blockable')
            ->get()
            ->pluck('blockable');

        if ($blockedCompanies->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No blocked companies found', []);
        }

        return ApiResponse::sendResponse(200, 'Blocked companies fetched successfully', BlockedCompanyResource::collection($blockedCompanies));
    }

    public function getBlockedRepresentatives(Request $request)
    {
        $doctor = $request->user();

        $blockedReps = DoctorBlock::where('doctors_id', $doctor->id)
            ->where('blockable_type', Representative::class)
            ->with('blockable')
            ->get()
            ->pluck('blockable');

        if ($blockedReps->isEmpty()) {
            return ApiResponse::sendResponse(200, 'No blocked representatives found', []);
        }

        return ApiResponse::sendResponse(200, 'Blocked representatives fetched successfully', BlockedRepsResource::collection($blockedReps));
    }

    public function contactUs()
    {

        $get_email_feedback = FeedbackEmail::select('email_feedback')->first();

        return ApiResponse::sendResponse(200, 'Email feedback fetched successfully', $get_email_feedback);
    }

    public function getCancelledAppointments(AppointmentStatusRefreshService $statusRefresh)
    {
        $doctor = $this->refreshDoctorAppointments($statusRefresh);
        // dd($doctor);
        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'cancelled')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        // dd($appointments);
        if ($appointments->isNotEmpty()) {
            return ApiResponse::sendResponse(200, 'Cancelled Appointments fetched successfully', DoctorAppointmentsResource::collection($appointments));
        }
        return ApiResponse::sendResponse(200, 'Cancelled Appointments Not Found', []);
    }

    public function getPendingAppointments(AppointmentStatusRefreshService $statusRefresh)
    {
        $doctor = $this->refreshDoctorAppointments($statusRefresh);
        // dd($doctor);
        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'pending')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        // dd($appointments);
        if ($appointments->isNotEmpty()) {
            return ApiResponse::sendResponse(200, 'Pending Appointments fetched successfully', DoctorAppointmentsResource::collection($appointments));
        }
        return ApiResponse::sendResponse(200, 'Pending Appointments Not Found', []);
    }

    public function getConfirmedAppointments(AppointmentStatusRefreshService $statusRefresh)
    {
        $doctor = $this->refreshDoctorAppointments($statusRefresh);
        // dd($doctor);
        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'confirmed')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
        // dd($appointments);
        if ($appointments->isNotEmpty()) {
            return ApiResponse::sendResponse(200, 'Confirmed Appointments fetched successfully', DoctorAppointmentsResource::collection($appointments));
        }
        return ApiResponse::sendResponse(200, 'Confirmed Appointments Not Found', []);
    }

    public function searchBlockList(Request $request)
    {

        $doctor = auth()->user()->id;

        // dd($doctor);
        $filters = $request->only(['name']);

        $blocked = DoctorBlock::with('blockable')
            ->where('doctors_id', $doctor)
            ->filter($filters)
            ->get();
        // dd($serached);

        if ($blocked->isEmpty()) {
            return ApiResponse::sendResponse(404, 'No blocked users found', []);
        }
        return ApiResponse::sendResponse(200, 'Blocked users fetched successfully', BlockSearchResource::collection($blocked));
    }

    private function refreshDoctorAppointments(AppointmentStatusRefreshService $statusRefresh): int
    {
        $doctorId = (int) auth()->id();
        $statusRefresh->refreshForDoctor($doctorId);

        return $doctorId;
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
