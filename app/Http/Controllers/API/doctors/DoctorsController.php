<?php

namespace App\Http\Controllers\API\doctors;

use App\Events\SendNotificationEvent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppAvailableTimeResource;
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
use App\Models\DoctorAvailability;
use App\Models\DoctorBlock;
use App\Models\Doctors;
use App\Models\FeedbackEmail;
use App\Models\Notification;
use App\Models\Representative;
use App\Models\Specialty;
use App\Models\User;
use App\Services\AppointmentStatusRefreshService;
use App\Services\DoctorBusyStatusService;
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
                    $query->where('status', 'available')
                        ->select('id', 'doctors_id', 'date', 'start_time', 'end_time', 'ends_next_day', 'status');
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

    public function editStatus(Request $request, DoctorBusyStatusService $doctorBusyStatus)
    {
        $doctor = $request->user();

        $validated = Validator::make($request->all(), [
            'status' => ['required', 'in:active,busy'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if ($validated->fails()) {
            return ApiResponse::sendResponse(422, $validated->messages()->first(), []);
        }

        $status = (string) $request->input('status');

        if ($status === 'active') {
            $doctor->status = 'active';
            $doctor->from_date = null;
            $doctor->to_date = null;
            $doctor->save();

            return ApiResponse::sendResponse(200, 'Doctor status updated successfully', new DoctorResource($doctor));
        }

        $busyValidationError = $doctorBusyStatus->validateBusyRangeInput(
            $request->input('from_date'),
            $request->input('to_date')
        );
        if ($busyValidationError !== null) {
            return ApiResponse::sendResponse(422, $busyValidationError, []);
        }

        $doctor->status = 'busy';
        $doctor->from_date = $request->input('from_date');
        $doctor->to_date = $request->input('to_date');
        $doctor->save();

        $appointments = Appointment::with('representative')
            ->where('doctors_id', $doctor->id)
            ->where('status', 'pending')
            ->whereBetween('date', [$doctor->from_date, $doctor->to_date])
            ->get();

        Appointment::whereIn('id', $appointments->pluck('id'))
            ->update(['status' => 'cancelled', 'cancelled_by' => 'Dr.' . $doctor->name]);

        $dateBusyFrom = Carbon::parse($doctor->from_date, 'Africa/Cairo')->format('Y-m-d');
        $dateBusyTo = Carbon::parse($doctor->to_date, 'Africa/Cairo')->format('Y-m-d');

        $representatives = $appointments
            ->pluck('representative')
            ->unique('id');
        foreach ($representatives as $rep) {
            $dedupeKey = sprintf(
                'doctor:%d:busy:%s:%s:cancelled:to:rep:%d',
                (int) $doctor->id,
                (string) $doctor->from_date,
                (string) $doctor->to_date,
                (int) $rep->id
            );

            event(new SendNotificationEvent(
                $rep,
                'Visit Cancelled Due to Doctor’s Custom Busy Period',
                'Dr. ' . $doctor->name .
                ' is unavailable from ' . $dateBusyFrom .
                ' to ' . $dateBusyTo .
                '. Your visit has been cancelled.',
                'reps',
                [],
                $dedupeKey
            ));
        }

        return ApiResponse::sendResponse(200, 'Doctor status updated successfully', new DoctorResource($doctor));
    }

    public function saveAvailableTimes(Request $request)
    {
        $doctor = $request->user();

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'string'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422,$validator->errors()->first(),[]);
        }
        $validated = $validator->validated();
        $normalizedDate = $this->normalizeAvailabilityDate($validated['date']);
        if (isset($normalizedDate['error'])) {
            return ApiResponse::sendResponse(422, $normalizedDate['error'], []);
        }

        $endsNextDay = (bool) ($validated['ends_next_day'] ?? false);
        $normalizedTimes = $this->normalizeAvailabilityTimes($validated['start_time'], $validated['end_time'], $endsNextDay);
        if (isset($normalizedTimes['error'])) {
            return ApiResponse::sendResponse(422, $normalizedTimes['error'], []);
        }

        if ($this->hasAvailabilityOverlap(
            (int) $doctor->id,
            $normalizedDate['date'],
            $normalizedTimes['start_time'],
            $normalizedTimes['end_time'],
            $normalizedTimes['ends_next_day']
        )) {
            return ApiResponse::sendResponse(422, 'This time conflicts with an existing availability', []);
        }

        $doctor->availableTimes()->create([
            'date' => $normalizedDate['date'],
            'start_time' => $normalizedTimes['start_time'],
            'end_time' => $normalizedTimes['end_time'],
            'ends_next_day' => $normalizedTimes['ends_next_day'],
            'status' => 'available',
        ]);

        return ApiResponse::sendResponse(200, 'Availabilities saved successfully', DoctorResource::make($doctor->load([
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            }
        ])));
    }

    public function updateAvailableTime(Request $request, $availability_id)
    {
        $doctor = $request->user();

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'string'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->errors()->first(), []);
        }

        $availability = $doctor->availableTimes()
            ->where('id', $availability_id)
            ->where('status', 'available')
            ->first();

        if (!$availability) {
            return ApiResponse::sendResponse(404, 'Availability not found or not editable', []);
        }

        $validated = $validator->validated();
        $normalizedDate = $this->normalizeAvailabilityDate($validated['date']);
        if (isset($normalizedDate['error'])) {
            return ApiResponse::sendResponse(422, $normalizedDate['error'], []);
        }

        $endsNextDay = (bool) ($validated['ends_next_day'] ?? false);
        $normalizedTimes = $this->normalizeAvailabilityTimes($validated['start_time'], $validated['end_time'], $endsNextDay);
        if (isset($normalizedTimes['error'])) {
            return ApiResponse::sendResponse(422, $normalizedTimes['error'], []);
        }

        $existingAvailabilityWeekday = $this->normalizeStoredAvailabilityWeekday((string) $availability->date);
        if ($existingAvailabilityWeekday === null) {
            $existingAvailabilityWeekday = strtolower(trim((string) $availability->date));
        }

        $ignoredAvailabilityIds = [(int) $availability->id];

        $isNoOpUpdate = $existingAvailabilityWeekday === $normalizedDate['date']
            && $availability->start_time === $normalizedTimes['start_time']
            && $availability->end_time === $normalizedTimes['end_time']
            && (bool) $availability->ends_next_day === $normalizedTimes['ends_next_day'];

        if ($this->hasAvailabilityOverlap(
            (int) $doctor->id,
            $normalizedDate['date'],
            $normalizedTimes['start_time'],
            $normalizedTimes['end_time'],
            $normalizedTimes['ends_next_day'],
            $ignoredAvailabilityIds
        )) {
            return ApiResponse::sendResponse(422, 'This time conflicts with an existing availability', []);
        }

        if (!$isNoOpUpdate && $this->hasActiveAppointmentOverlap(
            (int) $doctor->id,
            $normalizedDate['date'],
            $normalizedTimes['start_time'],
            $normalizedTimes['end_time'],
            $normalizedTimes['ends_next_day']
        )) {
            return ApiResponse::sendResponse(422, 'Cannot update availability that overlaps active appointments', []);
        }

        $availability->update([
            'date' => $normalizedDate['date'],
            'start_time' => $normalizedTimes['start_time'],
            'end_time' => $normalizedTimes['end_time'],
            'ends_next_day' => $normalizedTimes['ends_next_day'],
        ]);

        return ApiResponse::sendResponse(200, 'Availability updated successfully', new AppAvailableTimeResource($availability->fresh()));
    }

    public function deleteAvailableTime(Request $request, $availability_id)
    {
        $doctor = $request->user();

        $availability = $doctor->availableTimes()
            ->where('id', $availability_id)
            ->where('status', 'available')
            ->first();

        if (!$availability) {
            return ApiResponse::sendResponse(404, 'Availability not found or not deletable', []);
        }

        if ($this->hasActiveAppointmentOverlap(
            (int) $doctor->id,
            $availability->date,
            $availability->start_time,
            $availability->end_time,
            (bool) $availability->ends_next_day
        )) {
            return ApiResponse::sendResponse(422, 'Cannot delete availability that overlaps active appointments', []);
        }

        $availability->delete();

        return ApiResponse::sendResponse(200, 'Availability deleted successfully', []);
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
            'status' => ['nullable', 'in:cancelled,confirmed,pending,left,suspended,deleted'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'status' => 'Status',
            'search' => 'Search',
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
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                if ($search === '') {
                    return;
                }

                $searchTerm = '%' . $search . '%';

                $query->where(function ($searchQuery) use ($searchTerm) {
                    $searchQuery->where('appointment_code', 'like', $searchTerm)
                        ->orWhereHas('company', function ($companyQuery) use ($searchTerm) {
                            $companyQuery->where('name', 'like', $searchTerm);
                        })
                        ->orWhereHas('representative', function ($representativeQuery) use ($searchTerm) {
                            $representativeQuery->where('name', 'like', $searchTerm);
                        });
                });
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
        $dedupeKey = sprintf(
            'appointment:%d:doctor_cancelled:to:rep:%d',
            (int) $appointment->id,
            (int) $reps->id
        );
        // dd($reps);
        // $service->sendNotification($reps->fcm_token, $notify->title, $notify->body);
        event(new SendNotificationEvent(
            $reps,
            'Visit Cancelled by Doctor',
            'Your visit with Dr.' . $doctor->name . ' has been cancelled',
            'reps',
            [],
            $dedupeKey
        ));

        return ApiResponse::sendResponse(200, 'Appointment cancelled successfully', new DoctorAppointmentsResource($appointment));
    }

    public function filterAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'name' => 'Name',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $doctor = $this->refreshDoctorAppointments($statusRefresh);

        $filters = $request->only(['name']);

        $searched = Appointment::with(['representative', 'company'])
            ->where('doctors_id', $doctor)
            ->filter($filters)
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($searched);
        $items = FiltersResource::collection($searched->items());

        if ($searched->total() === 0) {
            return ApiResponse::sendResponse(404, 'No Appointments found', [], $pagination);
        }
        return ApiResponse::sendResponse(200, 'Appointments fetched successfully', $items, $pagination);
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

        if (config('notifications.debug', false)) {
            $this->logBookedNotificationDiagnostics((int) $doctor->id, $notifications);
        }
        
        if( $notifications->isEmpty() ) {
            return ApiResponse::sendResponse(200, 'No notifications found', []);
        }
        
        return ApiResponse::sendResponse(200, 'Notifications fetched successfully', NotificationsResource::collection($notifications));
    }

    private function logBookedNotificationDiagnostics(int $doctorId, $notifications): void
    {
        $bookedNotifications = $notifications
            ->filter(function ($notification) {
                $dedupeKey = (string) ($notification->dedupe_key ?? '');
                return preg_match('/^appointment:\d+:booked:/', $dedupeKey) === 1;
            })
            ->values();

        $duplicateKeyGroups = $bookedNotifications
            ->groupBy('dedupe_key')
            ->filter(fn($group) => $group->count() > 1)
            ->map(function ($group) {
                return [
                    'dedupe_key' => (string) $group->first()->dedupe_key,
                    'count' => $group->count(),
                    'notification_ids' => $group->pluck('id')->values()->all(),
                    'created_at' => $group->pluck('created_at')
                        ->map(fn($createdAt) => optional($createdAt)->toDateTimeString())
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $duplicateSemanticGroups = $bookedNotifications
            ->groupBy(function ($notification) {
                return hash('sha256', implode('|', [
                    (string) ($notification->title ?? ''),
                    (string) ($notification->body ?? ''),
                    (string) ($notification->target_type ?? ''),
                ]));
            })
            ->filter(function ($group) {
                return $group->count() > 1
                    && $group->pluck('dedupe_key')->filter()->unique()->count() > 1;
            })
            ->map(function ($group, $fingerprint) {
                return [
                    'semantic_fingerprint' => (string) $fingerprint,
                    'count' => $group->count(),
                    'notification_ids' => $group->pluck('id')->values()->all(),
                    'dedupe_keys' => $group->pluck('dedupe_key')->filter()->unique()->values()->all(),
                    'created_at' => $group->pluck('created_at')
                        ->map(fn($createdAt) => optional($createdAt)->toDateTimeString())
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        \Log::info('Doctor notifications booked diagnostics', [
            'doctor_id' => $doctorId,
            'returned_count' => $notifications->count(),
            'booked_count' => $bookedNotifications->count(),
            'duplicate_key_groups' => $duplicateKeyGroups,
            'duplicate_semantic_groups' => $duplicateSemanticGroups,
        ]);
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

        $repBlock = DoctorBlock::firstOrCreate([
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
            $dedupeKey = sprintf(
                'doctor:%d:block_rep:%d:to:rep:%d',
                (int) $doctor->id,
                (int) $repBlock->id,
                (int) $reps->id
            );

            event(new SendNotificationEvent(
                $reps,
                'Visit Cancelled Due to Doctor Blocking the Rep',
                'You’ve been blocked by Dr. ' . $doctor->name . ' Your visit has been cancelled.',
                'reps',
                [],
                $dedupeKey
            ));
        }


        return ApiResponse::sendResponse(200, 'blocked representative successfully', []);
    }


    public function blockCompany(Request $request, $companyId)
    {
        $doctor = $request->user();

        if (Company::where('id', $companyId)->exists()) {
            $companyBlock = DoctorBlock::firstOrCreate([
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
                $repBlock = DoctorBlock::firstOrCreate([
                    'doctors_id' => $doctor->id,
                    'blockable_id' => $reps->id,
                    'blockable_type' => Representative::class,
                ]);

                $dedupeKey = sprintf(
                    'doctor:%d:block_company:%d:block_rep:%d:to:rep:%d',
                    (int) $doctor->id,
                    (int) $companyBlock->id,
                    (int) $repBlock->id,
                    (int) $reps->id
                );

                event(new SendNotificationEvent(
                    $reps,
                    'Visit Cancelled Due to Doctor Blocking the Company',
                    'Dr. ' . $doctor->name . ' has blocked ' . $companyName->name . '. All your visits have been cancelled.',
                    'reps',
                    [],
                    $dedupeKey
                ));
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
            ->where('blockable_type', Company::class)
            ->first();

        if (!$companyBlock) {
            return ApiResponse::sendResponse(404, 'Block record not found', []);
        }

        $companyBlockId = (int) $companyBlock->id;
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
            $dedupeKey = sprintf(
                'doctor:%d:unblock_company:%d:to:rep:%d',
                (int) $doctor->id,
                $companyBlockId,
                (int) $rep->id
            );

            event(new SendNotificationEvent(
                $rep,
                'Company Unblocked',
                'Dr. ' . $doctor->name .
                ' has unblocked ' . $company->name .
                '. You can now book visits.',
                'reps',
                [],
                $dedupeKey
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
            $dedupeKey = sprintf(
                'doctor:%d:unblock_rep:%d:to:rep:%d',
                (int) $doctor->id,
                (int) $block->id,
                (int) $reps->id
            );

            event(new SendNotificationEvent(
                $reps,
                'Rep Unblock Notification',
                'The block has been removed by Dr. ' . $doctor->name . ' You can now book visits.',
                'reps',
                [],
                $dedupeKey
            ));
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

    public function getCancelledAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
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
        $doctor = $this->refreshDoctorAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'cancelled')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = DoctorAppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Cancelled Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Cancelled Appointments Not Found', [], $pagination);
    }

    public function getPendingAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
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
        $doctor = $this->refreshDoctorAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'pending')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = DoctorAppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Pending Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Pending Appointments Not Found', [], $pagination);
    }

    public function getConfirmedAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
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
        $doctor = $this->refreshDoctorAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('doctors_id', $doctor)
            ->where('status', 'confirmed')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = DoctorAppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Confirmed Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Confirmed Appointments Not Found', [], $pagination);
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

    private function normalizeAvailabilityTimes(string $startTime, string $endTime, bool $endsNextDay): array
    {
        $normalizedStartTime = $this->normalizeAvailabilityTime($startTime);
        $normalizedEndTime = $this->normalizeAvailabilityTime(
            $this->normalizeEndTimeBoundary((string) $endTime)
        );

        if ($normalizedStartTime === null || $normalizedEndTime === null) {
            return ['error' => 'Invalid time format, please use hh:mm AM/PM or HH:mm'];
        }

        if ($normalizedStartTime === $normalizedEndTime) {
            return ['error' => 'Start time must be before end time'];
        }

        if (!$endsNextDay && $normalizedStartTime > $normalizedEndTime) {
            return ['error' => 'Start time must be before end time'];
        }

        // Allow lenient client behavior: if flag is true but range is same-day, normalize to same-day.
        if ($endsNextDay && $normalizedEndTime > $normalizedStartTime) {
            $endsNextDay = false;
        }

        return [
            'start_time' => $normalizedStartTime,
            'end_time' => $normalizedEndTime,
            'ends_next_day' => $endsNextDay,
        ];
    }

    private function normalizeEndTimeBoundary(string $endTime): string
    {
        $trimmedEndTime = trim($endTime);

        if (preg_match('/^24:00(?::00)?$/', $trimmedEndTime) === 1) {
            return '23:59:00';
        }

        return $trimmedEndTime;
    }

    private function normalizeAvailabilityDate(string $date): array
    {
        $normalizedWeekday = $this->normalizeStoredAvailabilityWeekday($date);
        if ($normalizedWeekday === null) {
            return ['error' => 'Invalid date format, please use weekday name or YYYY-MM-DD'];
        }

        return ['date' => $normalizedWeekday];
    }

    private function normalizeAvailabilityTime(string $time): ?string
    {
        $trimmedTime = trim($time);

        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5][0-9])\s*([AaPp][Mm])$/', $trimmedTime, $matches) === 1) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $meridiem = strtoupper($matches[3]);

            if ($meridiem === 'AM' && $hour === 12) {
                $hour = 0;
            } elseif ($meridiem === 'PM' && $hour !== 12) {
                $hour += 12;
            }

            return sprintf('%02d:%02d:00', $hour, $minute);
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $trimmedTime, $matches) === 1) {
            return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function hasAvailabilityOverlap(
        int $doctorId,
        string $date,
        string $startTime,
        string $endTime,
        bool $endsNextDay,
        array $ignoredAvailabilityIds = []
    ): bool {
        $targetWeekday = $this->normalizeStoredAvailabilityWeekday($date);
        if ($targetWeekday === null) {
            return false;
        }

        $targetInterval = $this->buildWeekdayInterval($targetWeekday, $startTime, $endTime, $endsNextDay);

        $candidateAvailabilities = DoctorAvailability::query()
            ->where('doctors_id', $doctorId)
            ->where('status', 'available')
            ->when(!empty($ignoredAvailabilityIds), function ($query) use ($ignoredAvailabilityIds) {
                $query->whereNotIn('id', $ignoredAvailabilityIds);
            })
            ->get(['id', 'date', 'start_time', 'end_time', 'ends_next_day']);

        foreach ($candidateAvailabilities as $availability) {
            $existingWeekday = $this->normalizeStoredAvailabilityWeekday((string) $availability->date);
            if ($existingWeekday === null) {
                continue;
            }

            $existingEndsNextDay = (bool) $availability->ends_next_day
                || $this->isLegacyOvernightInterval((string) $availability->start_time, (string) $availability->end_time);

            $existingInterval = $this->buildWeekdayInterval(
                $existingWeekday,
                (string) $availability->start_time,
                (string) $availability->end_time,
                $existingEndsNextDay
            );

            if ($this->recurringIntervalsOverlap(
                $targetInterval['start_at'],
                $targetInterval['end_at'],
                $existingInterval['start_at'],
                $existingInterval['end_at']
            )) {
                return true;
            }
        }

        return false;
    }

    private function hasActiveAppointmentOverlap(
        int $doctorId,
        string $date,
        string $startTime,
        string $endTime,
        bool $endsNextDay
    ): bool {
        $targetWeekday = $this->normalizeStoredAvailabilityWeekday($date);
        if ($targetWeekday === null) {
            return false;
        }

        $targetInterval = $this->buildWeekdayInterval($targetWeekday, $startTime, $endTime, $endsNextDay);
        $today = Carbon::today()->toDateString();

        $candidateAppointments = Appointment::query()
            ->where('doctors_id', $doctorId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDate('date', '>=', $today)
            ->get(['id', 'date', 'start_time', 'end_time']);

        foreach ($candidateAppointments as $appointment) {
            $appointmentEndsNextDay = $this->isLegacyOvernightInterval(
                (string) $appointment->start_time,
                (string) $appointment->end_time
            );

            $appointmentInterval = $this->buildWeekdayInterval(
                strtolower(Carbon::parse($appointment->date)->format('l')),
                Carbon::parse($appointment->start_time)->format('H:i:s'),
                Carbon::parse($appointment->end_time)->format('H:i:s'),
                $appointmentEndsNextDay
            );

            if ($this->recurringIntervalsOverlap(
                $targetInterval['start_at'],
                $targetInterval['end_at'],
                $appointmentInterval['start_at'],
                $appointmentInterval['end_at']
            )) {
                return true;
            }
        }

        return false;
    }

    private function buildWeekdayInterval(string $weekday, string $startTime, string $endTime, bool $endsNextDay): array
    {
        $weekdayMap = $this->weekdayToIndexMap();
        $weekdayIndex = $weekdayMap[$weekday] ?? 0;
        $anchorSunday = Carbon::create(2026, 1, 4, 0, 0, 0);

        [$startHour, $startMinute, $startSecond] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute, $endSecond] = array_map('intval', explode(':', $endTime));

        $startAt = $anchorSunday->copy()->addDays($weekdayIndex)->setTime($startHour, $startMinute, $startSecond);
        $endAt = $anchorSunday->copy()->addDays($weekdayIndex)->setTime($endHour, $endMinute, $endSecond);

        if ($endsNextDay) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function intervalsOverlap(Carbon $leftStartAt, Carbon $leftEndAt, Carbon $rightStartAt, Carbon $rightEndAt): bool
    {
        return $leftStartAt->lt($rightEndAt) && $leftEndAt->gt($rightStartAt);
    }

    private function recurringIntervalsOverlap(Carbon $leftStartAt, Carbon $leftEndAt, Carbon $rightStartAt, Carbon $rightEndAt): bool
    {
        foreach ([-7, 0, 7] as $shiftDays) {
            if ($this->intervalsOverlap(
                $leftStartAt,
                $leftEndAt,
                $rightStartAt->copy()->addDays($shiftDays),
                $rightEndAt->copy()->addDays($shiftDays)
            )) {
                return true;
            }
        }

        return false;
    }

    private function normalizeStoredAvailabilityWeekday(string $date): ?string
    {
        $trimmedDate = trim($date);
        if ($trimmedDate === '') {
            return null;
        }

        $normalizedWeekday = strtolower($trimmedDate);
        if (array_key_exists($normalizedWeekday, $this->weekdayToIndexMap())) {
            return $normalizedWeekday;
        }

        try {
            $normalizedDate = Carbon::createFromFormat('Y-m-d', $trimmedDate);
        } catch (\Exception $exception) {
            return null;
        }

        if ($normalizedDate->format('Y-m-d') !== $trimmedDate) {
            return null;
        }

        return strtolower($normalizedDate->format('l'));
    }

    private function weekdayToIndexMap(): array
    {
        return [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];
    }

    private function isLegacyOvernightInterval(string $startTime, string $endTime): bool
    {
        return $endTime <= $startTime;
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
