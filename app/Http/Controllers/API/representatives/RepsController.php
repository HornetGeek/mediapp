<?php

namespace App\Http\Controllers\API\representatives;

use App\Events\SendNotificationEvent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentsResource;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\NotificationsResource;
use App\Http\Resources\RepsResource;
use App\Http\Resources\SpecialtiesResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorAvailability;
use App\Models\DoctorBlock;
use App\Models\Doctors;
use App\Models\Notification;
use App\Models\Representative;
use App\Models\Specialty;
use App\Services\AppointmentBookingInputService;
use App\Services\AppointmentCancellationAndBookedService;
use App\Services\AppointmentStatusRefreshService;
use App\Services\DoctorBusyStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RepsController extends Controller
{
    private const BOOKING_TIMEZONE = 'Africa/Cairo';

    public function getRepsProfile(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [], [
            'date' => 'Date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $rep = auth()->user();

        if ($rep) {
            $representativeId = $this->refreshRepresentativeAppointments($statusRefresh);
            $targetDate = $request->filled('date')
                ? (string) $request->input('date')
                : Carbon::now(self::BOOKING_TIMEZONE)->toDateString();

            $rep->load(['company', 'areas', 'lines']);

            $dailyVisitsLimit = $this->resolveRepresentativeDailyVisitsLimit($rep);
            $usedVisitsToday = $this->countUsedVisitsForDate($representativeId, $targetDate);
            $remainingVisitsToday = max(0, $dailyVisitsLimit - $usedVisitsToday);

            $rep->setAttribute('daily_visits_limit', $dailyVisitsLimit);
            $rep->setAttribute('used_visits_today', $usedVisitsToday);
            $rep->setAttribute('remaining_visits_today', $remainingVisitsToday);

            return ApiResponse::sendResponse(200, 'Representative Profile fetched successfully', new RepsResource($rep));
        }

        return ApiResponse::sendResponse(404, 'Representative not found', []);
    }

    public function getVisitsBalance(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'date' => 'Date',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $rep = auth()->user();

        if (!$rep) {
            return ApiResponse::sendResponse(404, 'Representative not found', []);
        }

        $representativeId = $this->refreshRepresentativeAppointments($statusRefresh);
        $targetDate = $request->filled('date')
            ? (string) $request->input('date')
            : Carbon::now(self::BOOKING_TIMEZONE)->toDateString();
        $perPage = (int) $request->input('per_page', 10);

        $rep->loadMissing('company');

        $dailyVisitsLimit = $this->resolveRepresentativeDailyVisitsLimit($rep);
        $usedVisitsToday = $this->countUsedVisitsForDate($representativeId, $targetDate);
        $remainingVisitsToday = max(0, $dailyVisitsLimit - $usedVisitsToday);
        $consumedAppointments = Appointment::with(['doctor.specialty', 'representative', 'company'])
            ->where('representative_id', $representativeId)
            ->whereDate('date', $targetDate)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);
        $pagination = $this->buildPaginationMeta($consumedAppointments);

        return ApiResponse::sendResponse(200, 'Visits balance fetched successfully', [
            'daily_visits_limit' => $dailyVisitsLimit,
            'used_visits_today' => $usedVisitsToday,
            'remaining_visits_today' => $remainingVisitsToday,
            'consumed_appointments' => AppointmentsResource::collection($consumedAppointments->items()),
        ], $pagination);
    }

    public function getDoctorProfile($doctor_id, DoctorBusyStatusService $doctorBusyStatus)
    {
        $doctor = Doctors::with([
            'specialty',
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            },
        ])->find($doctor_id);

        if (!$doctor) {
            return ApiResponse::sendResponse(404, 'Doctor not found', []);
        }

        $doctorBusyStatus->normalizeDoctorBusyState($doctor);

        return ApiResponse::sendResponse(200, 'Doctor Profile fetched successfully', new DoctorResource($doctor));
    }

    public function getAvailableTimeForDoctor(Request $request, DoctorBusyStatusService $doctorBusyStatus)
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'search' => 'Search',
            'specialty' => 'Specialty',
            'specialty_id' => 'Specialty',
            'address_1' => 'Address',
            'date' => 'Date',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $representative = auth()->user();
        $doctors = Doctors::with([
            'specialty',
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            },
            'favoredByReps' => function ($query) {
                $query->where('representative_id', auth()->id());
            }
        ])
            ->whereDoesntHave('blocks', function ($q) use ($representative) {
                $q->where(function ($sub) use ($representative) {
                    $sub->where('blockable_type', Representative::class)
                        ->where('blockable_id', $representative->id);
                })->orWhere(function ($sub) use ($representative) {
                    $sub->where('blockable_type', Company::class)
                        ->where('blockable_id', $representative->company_id);
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                if ($search === '') {
                    return;
                }

                $searchTerm = '%' . $search . '%';
                $query->where(function ($searchQuery) use ($searchTerm) {
                    $searchQuery->where('name', 'like', $searchTerm)
                        ->orWhere('address_1', 'like', $searchTerm)
                        ->orWhereHas('specialty', function ($specialtyQuery) use ($searchTerm) {
                            $specialtyQuery->where('name', 'like', $searchTerm);
                        });
                });
            })
            ->when($request->filled('specialty_id'), function ($query) use ($request) {
                $query->where('specialty_id', (int) $request->input('specialty_id'));
            })
            ->when($request->filled('specialty'), function ($query) use ($request) {
                $specialty = trim((string) $request->input('specialty'));
                if ($specialty === '') {
                    return;
                }

                $query->whereHas('specialty', function ($specialtyQuery) use ($specialty) {
                    $specialtyQuery->where('name', 'like', '%' . $specialty . '%');
                });
            })
            ->when($request->filled('address_1'), function ($query) use ($request) {
                $address = trim((string) $request->input('address_1'));
                if ($address === '') {
                    return;
                }

                $query->where('address_1', 'like', '%' . $address . '%');
            })
            ->paginate($perPage);

        $doctorCollection = collect($doctors->items());
        $doctorBusyStatus->normalizeDoctorCollectionBusyState($doctorCollection);
        $pagination = $this->buildPaginationMeta($doctors);

        if ($doctors->total() > 0) {
            return ApiResponse::sendResponse(200, 'Doctors found Successfully', DoctorResource::collection($doctorCollection), $pagination);
        }

        return ApiResponse::sendResponse(200, 'Doctors Not Found', [], $pagination);
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

    public function filterDoctors(Request $request, DoctorBusyStatusService $doctorBusyStatus)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'name' => 'Name',
            'location' => 'Location',
            'specialty_id' => 'Specialty',
            'date' => 'Date',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $rep = $request->user();
        $filters = $request->only(['name', 'location', 'specialty_id']);

        $doctors = Doctors::with([
            'specialty',
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            },
        ])
            ->filter($filters)
            ->whereDoesntHave('blocks', function ($q) use ($rep) {
                $q->where(function ($q2) use ($rep) {
                    $q2->where('blockable_type', 'representative')
                        ->where('blockable_id', $rep->id);
                })
                    ->orWhere(function ($q2) use ($rep) {
                        $q2->where('blockable_type', 'company')
                            ->where('blockable_id', $rep->company_id);
                    });
            })
            ->paginate($perPage);

        $doctorCollection = collect($doctors->items());
        $doctorBusyStatus->normalizeDoctorCollectionBusyState($doctorCollection);
        $pagination = $this->buildPaginationMeta($doctors);

        if ($doctors->total() === 0) {
            return ApiResponse::sendResponse(404, 'No doctors found', [], $pagination);
        }

        return ApiResponse::sendResponse(200, 'Doctors filtered successfully', DoctorResource::collection($doctorCollection), $pagination);
    }

    public function bookAppointment(
        Request $request,
        DoctorBusyStatusService $doctorBusyStatus,
        AppointmentBookingInputService $bookingInput,
        AppointmentStatusRefreshService $statusRefresh
    )
    {
        $duplicateSlotMessage = 'This time slot already has an active appointment (pending or confirmed).';

        $validated = Validator::make($request->all(), [
            'doctors_id' => 'required|exists:doctors,id',
            'date' => 'required|string',
            'start_time' => 'required|string',
        ]);



        if ($validated->fails()) {
            // return ApiResponse::sendResponse(422, 'Validation Error', $validated->messages()->all());
            return ApiResponse::sendResponse(422, $validated->messages()->first(), []);
        }

        $normalizedSlot = $bookingInput->buildSlot(
            (string) $request->input('date'),
            (string) $request->input('start_time'),
            5
        );
        if (isset($normalizedSlot['error'])) {
            return ApiResponse::sendResponse(422, (string) $normalizedSlot['error'], []);
        }

        $date = (string) $normalizedSlot['date'];
        $start = $normalizedSlot['start_at'];
        $end = $normalizedSlot['end_at'];
        $slotStartTime = (string) $normalizedSlot['start_time'];
        $slotEndTime = (string) $normalizedSlot['end_time'];

        $nowInCairo = Carbon::now(self::BOOKING_TIMEZONE);
        if ($start->lessThanOrEqualTo($nowInCairo)) {
            return ApiResponse::sendResponse(422, 'Cannot book an appointment in the past', []);
        }

        $representative = $request->user();
        $statusRefresh->refreshForRepresentative((int) $representative->id);

        // Check if there datetime booked
        $hasActiveSlotConflict = Appointment::where('doctors_id', $request->doctors_id)
            ->where('date', $date)
            ->where(function ($query) use ($slotStartTime, $slotEndTime) {
                $query->where(function ($q) use ($slotStartTime, $slotEndTime) {
                    $q->whereRaw('TIME(start_time) < ?', [$slotEndTime])
                        ->whereRaw('TIME(end_time) > ?', [$slotStartTime]);
                });
                $query->orWhereRaw('TIME(start_time) = ?', [$slotStartTime]);
            })
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        // dd($exists);

        if ($hasActiveSlotConflict) {
            return ApiResponse::sendResponse(409, $duplicateSlotMessage, []);
        }

        // Check how many active appointments the representative has on the requested booking date.
        $companyId = $representative->company_id;
        $representativeId = (int) $representative->id;

        $appointmentCount = $this->countUsedVisitsForDate($representativeId, (string) $date);
        $maxAppointmentsPerDay = $this->resolveRepresentativeDailyVisitsLimit($representative);

        if ($appointmentCount >= $maxAppointmentsPerDay) {
            return ApiResponse::sendResponse(403, 'You have reached the maximum number of appointments allowed for the selected date', []);
        }

        // Check if doctor is busy
        $doctor = Doctors::findOrFail($request->doctors_id);
        $doctorBusyStatus->normalizeDoctorBusyState($doctor);
        $bookingDate = $date;
        if ($doctorBusyStatus->isDateWithinBusyPeriod($doctor, $bookingDate)) {
            return ApiResponse::sendResponse(403,'Doctor is busy during the selected period',[]);
        }

        $DoctorBlocks = DoctorBlock::where('doctors_id', $request->doctors_id)->get();

        foreach ($DoctorBlocks as $block) {
            if ($block->blockable_type === 'App\Models\Representative' && $block->blockable_id == $representative->id) {
                return ApiResponse::sendResponse(403, 'You are blocked by this doctor', []);
            }
            if ($block->blockable_type === 'App\Models\Company' && $block->blockable_id == $representative->company_id) {
                return ApiResponse::sendResponse(403, 'Your company is blocked by this doctor', []);
            }
        }

        $company = Company::find($companyId);
        if ($company->status === 'inactive') {
            return ApiResponse::sendResponse(403, 'Your company is inactive. You cannot book appointments.', []);
        }
        $appointmentStatusWithDoctor = Appointment::where('doctors_id', $request->doctors_id)
            ->where('representative_id', auth()->id())
            ->whereIn('status', ['pending'])
            ->first();


        if ($appointmentStatusWithDoctor) {
            return ApiResponse::sendResponse(403, 'You cannot book appointment with this doctor, because you have previous book not completed', []);
        }

        if (!$this->isBookingSlotWithinDoctorAvailability((int) $request->doctors_id, $start, $end)) {
            return ApiResponse::sendResponse(422, 'Requested time is outside doctor availability', []);
        }

        try {
            $appointment = Appointment::create([
                'doctors_id' => $request->doctors_id,
                'representative_id' => auth()->id(),
                'start_time' => $slotStartTime,
                'end_time' => $slotEndTime,
                'date' => $date,
                'status' => "pending",
                'company_id' => auth()->user()->company_id

            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateActiveSlotViolation($exception)) {
                return ApiResponse::sendResponse(409, $duplicateSlotMessage, []);
            }

            throw $exception;
        }
        $appointment->load(['doctor', 'representative', 'company']);

        $doctor = $appointment->doctor;
        $dateTime = $start->format('Y-m-d h:i a');
        $dedupeKey = sprintf(
            'appointment:%d:booked:to:doctor:%d',
            (int) $appointment->id,
            (int) $doctor->id
        );

        if (config('notifications.debug', false)) {
            \Log::info('Booked notification dispatch debug', [
                'appointment_id' => (int) $appointment->id,
                'doctor_id' => (int) $doctor->id,
                'rep_id' => (int) auth()->id(),
                'date' => (string) $date,
                'slot_start' => $slotStartTime,
                'slot_end' => $slotEndTime,
                'dedupe_key' => $dedupeKey,
            ]);
        }

        event(new SendNotificationEvent(
            $doctor,
            'New visit booked.',
            'New visit booked with ' . auth()->user()->name . ' at ' . $dateTime,
            'doctor',
            [],
            $dedupeKey
        ));
      
      
        return ApiResponse::sendResponse(201, 'Appointment booked successfully', new AppointmentsResource($appointment));
    }

    private function isDuplicateActiveSlotViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return str_contains($message, 'appointments_active_slot_unique')
                || str_contains($message, 'unique constraint failed: appointments.doctors_id, appointments.date, appointments.start_time, appointments.slot_lock')
                || $driverCode === '1062';
        }

        return str_contains($message, 'appointments_active_slot_unique')
            || str_contains($message, 'unique constraint failed: appointments.doctors_id, appointments.date, appointments.start_time, appointments.slot_lock');
    }

    private function isBookingSlotWithinDoctorAvailability(
        int $doctorId,
        Carbon $slotStartAt,
        Carbon $slotEndAt
    ): bool {
        $availabilities = DoctorAvailability::query()
            ->where('doctors_id', $doctorId)
            ->where('status', 'available')
            ->get(['date', 'start_time', 'end_time', 'ends_next_day']);

        foreach ($availabilities as $availability) {
            if ($this->availabilityCoversSlot($availability, $slotStartAt, $slotEndAt)) {
                return true;
            }
        }

        return false;
    }

    private function availabilityCoversSlot(
        DoctorAvailability $availability,
        Carbon $slotStartAt,
        Carbon $slotEndAt
    ): bool {
        $anchorDates = [
            $slotStartAt->copy()->startOfDay(),
            $slotStartAt->copy()->subDay()->startOfDay(),
        ];

        foreach ($anchorDates as $anchorDate) {
            if (!$this->availabilityMatchesAnchorDate((string) $availability->date, $anchorDate)) {
                continue;
            }

            $availabilityInterval = $this->buildAvailabilityInterval($availability, $anchorDate);
            if ($availabilityInterval === null) {
                continue;
            }

            if (
                $slotStartAt->greaterThanOrEqualTo($availabilityInterval['start_at'])
                && $slotEndAt->lessThanOrEqualTo($availabilityInterval['end_at'])
            ) {
                return true;
            }
        }

        return false;
    }

    private function availabilityMatchesAnchorDate(string $availabilityDate, Carbon $anchorDate): bool
    {
        $trimmedAvailabilityDate = trim($availabilityDate);
        if ($trimmedAvailabilityDate === '') {
            return false;
        }

        $normalizedWeekday = strtolower($trimmedAvailabilityDate);
        if ($normalizedWeekday === strtolower($anchorDate->format('l'))) {
            return true;
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $trimmedAvailabilityDate, self::BOOKING_TIMEZONE);
        } catch (\Throwable $exception) {
            return false;
        }

        if ($parsedDate->format('Y-m-d') !== $trimmedAvailabilityDate) {
            return false;
        }

        return $parsedDate->toDateString() === $anchorDate->toDateString();
    }

    private function buildAvailabilityInterval(DoctorAvailability $availability, Carbon $anchorDate): ?array
    {
        $startTimeParts = $this->parseStoredTime((string) $availability->start_time);
        $endTimeParts = $this->parseStoredTime((string) $availability->end_time);
        if ($startTimeParts === null || $endTimeParts === null) {
            return null;
        }

        $startAt = $anchorDate->copy()->setTime($startTimeParts[0], $startTimeParts[1], $startTimeParts[2]);
        $endAt = $anchorDate->copy()->setTime($endTimeParts[0], $endTimeParts[1], $endTimeParts[2]);

        $isOvernight = (bool) $availability->ends_next_day
            || ((string) $availability->end_time <= (string) $availability->start_time);
        if ($isOvernight) {
            $endAt->addDay();
        }

        return [
            'start_at' => $startAt,
            'end_at' => $endAt,
        ];
    }

    private function parseStoredTime(string $time): ?array
    {
        $trimmedTime = trim($time);
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $trimmedTime, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    public function getRepsAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:cancelled,confirmed,pending,left,suspended,deleted'],
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'status' => 'Status',
            'search' => 'Search',
            'date' => 'Date',
            'specialty' => 'Specialty',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $rep = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor', 'company'])
            ->where('representative_id', $rep)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('date', $request->input('date'));
            })
            ->when($request->filled('specialty'), function ($query) use ($request) {
                $specialty = trim((string) $request->input('specialty'));

                if ($specialty === '') {
                    return;
                }

                $query->whereHas('doctor.specialty', function ($specialtyQuery) use ($specialty) {
                    $specialtyQuery->where('name', 'like', '%' . $specialty . '%');
                });
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
                        ->orWhereHas('doctor', function ($doctorQuery) use ($searchTerm) {
                            $doctorQuery->where('name', 'like', $searchTerm);
                        });
                });
            })
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Booked Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Appointments Not Found', [], $pagination);
    }

    public function completedBooking($book_id, AppointmentCancellationAndBookedService $service)
    {
        $reps = auth()->user();

        return $service->completed($book_id, $reps);
    }
    public function cancellationBooking($book_id, AppointmentCancellationAndBookedService $service, AppointmentStatusRefreshService $statusRefresh)
    {
        $reps = auth()->user();
        $this->refreshRepresentativeAppointments($statusRefresh);

        return $service->cancel($book_id, $reps);
    }

    public function deleteAppointment($book_id)
    {
        $reps = auth()->user();

        $appointment = Appointment::where('id', $book_id)
            ->where('representative_id', $reps->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::sendResponse(404, 'Appointment not found or not yours', []);
        }

        $appointment->delete();

        return ApiResponse::sendResponse(200, 'Appointment deleted successfully', []);
    }

    public function getNotifications()
    {
        $reps = auth()->user();
        $notifications = $reps->notifications()
            ->orderBy('created_at', 'desc')
            ->get();
        return ApiResponse::sendResponse(200, 'Notifications fetched successfully', NotificationsResource::collection($notifications));
    }

    public function markAllNotificationsAsRead()
    {
        $reps = auth()->user();

        $updatedCount = Notification::where('notifiable_id', $reps->id)
            ->where('notifiable_type', Representative::class)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResponse::sendResponse(200, 'All unread notifications marked as read successfully', [
            'updated_count' => $updatedCount,
        ]);
    }

    public function markNotificationAsRead($notification_id)
    {
        $reps = auth()->user();

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $reps->id)
            ->where('notifiable_type', Representative::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->update(['is_read' => true]);

        return ApiResponse::sendResponse(200, 'Notification marked as read successfully', new NotificationsResource($notification));
    }

    public function deleteNotification($notification_id)
    {
        $reps = auth()->user();

        $notification = Notification::where('id', $notification_id)
            ->where('notifiable_id', $reps->id)
            ->where('notifiable_type', Representative::class)
            ->first();

        if (!$notification) {
            return ApiResponse::sendResponse(404, 'Notification not found or not yours', []);
        }

        $notification->delete();

        return ApiResponse::sendResponse(200, 'Notification deleted successfully', []);
    }

    public function clearAllNotifications(Request $request)
    {
        $reps = auth()->user();
        // dd($doctor);

        $reps->notifications()->delete();

        return ApiResponse::sendResponse(200, 'All notifications cleared successfully', []);
    }

    public function getAppointmentsByStatus(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:cancelled,confirmed,pending,left,suspended,deleted'],
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
        $reps = $this->refreshRepresentativeAppointments($statusRefresh);

        $status = $request->input('status');

        $appointments = Appointment::query()
            ->where('representative_id', $reps)
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Not found', [], $pagination);
    }

    public function changeAppointmentStatus(
        Request $request,
        AppointmentCancellationAndBookedService $service,
        AppointmentStatusRefreshService $statusRefresh
    )
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => ['required', 'integer'],
        ], [], [
            'appointment_id' => 'Appointment',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $reps = auth()->user();
        $this->refreshRepresentativeAppointments($statusRefresh);

        $appointmentId = (int) $request->input('appointment_id');

        return $service->changeStatus($appointmentId, $reps);
    }

    public function getDoctorsBySpeciality(Request $request, DoctorBusyStatusService $doctorBusyStatus)
    {
        $validator = Validator::make($request->all(), [
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'specialty_id' => 'Specialty',
            'date' => 'Date',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $representative = auth()->user();
        $speciality_id = $request->input('specialty_id');

        $doctors = Doctors::with([
            'specialty',
            'availableTimes' => function ($query) {
                $query->where('status', 'available');
            },
        ])
            ->when($speciality_id, function ($query, $speciality_id) {
                $query->where('specialty_id', $speciality_id);
            })
            ->whereDoesntHave('blocks', function ($q) use ($representative) {
                $q->where(function ($sub) use ($representative) {
                    $sub->where('blockable_type', Representative::class)
                        ->where('blockable_id', $representative->id);
                })->orWhere(function ($sub) use ($representative) {
                    $sub->where('blockable_type', Company::class)
                        ->where('blockable_id', $representative->company_id);
                });
            })
            ->paginate($perPage);

        $doctorCollection = collect($doctors->items());
        $doctorBusyStatus->normalizeDoctorCollectionBusyState($doctorCollection);
        $pagination = $this->buildPaginationMeta($doctors);

        if ($doctors->total() > 0) {
            return ApiResponse::sendResponse(200, 'Doctors fetched successfully', DoctorResource::collection($doctorCollection), $pagination);
        }

        return ApiResponse::sendResponse(200, 'No doctors found', [], $pagination);
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
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->where('status', 'cancelled')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

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
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->where('status', 'pending')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

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
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->where('status', 'confirmed')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Confirmed Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Confirmed Appointments Not Found', [], $pagination);
    }

    public function getLeftingAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
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
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->where('status', 'left')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Lefting Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Lefting Appointments Not Found', [], $pagination);
    }

    public function filterAppointmentsByDateAndSpecialty(Request $request, AppointmentStatusRefreshService $statusRefresh)
    {
        $validator = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [], [
            'date' => 'Date',
            'specialty_id' => 'Specialty',
            'page' => 'Page',
            'per_page' => 'Per Page',
        ]);

        if ($validator->fails()) {
            return ApiResponse::sendResponse(422, $validator->messages()->first(), []);
        }

        $perPage = (int) $request->input('per_page', 10);
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);
        $date = $request->input('date');
        $specialty_id = $request->input('specialty_id');

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->when($date, function ($query, $date) {
                $query->where('date', $date);
            })
            ->when($specialty_id, function ($query, $specialty_id) {
                $query->whereHas('doctor', function ($q) use ($specialty_id) {
                    $q->where('specialty_id', $specialty_id);
                });
            })
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Filtered Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Filtered Appointments Not Found', [], $pagination);
    }

    public function getSuspendedAppointments(Request $request, AppointmentStatusRefreshService $statusRefresh)
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
        $representative = $this->refreshRepresentativeAppointments($statusRefresh);

        $appointments = Appointment::with(['representative', 'doctor'])
            ->where('representative_id', $representative)
            ->where('status', 'suspended')
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate($perPage);

        $pagination = $this->buildPaginationMeta($appointments);
        $items = AppointmentsResource::collection($appointments->items());

        if ($appointments->total() > 0) {
            return ApiResponse::sendResponse(200, 'Suspended Appointments fetched successfully', $items, $pagination);
        }
        return ApiResponse::sendResponse(200, 'Suspended Appointments Not Found', [], $pagination);
    }

    // public function getAppointmentsNowAndBeforeTwoDay()
    // {
    //     $representativeId = auth()->id();

    //     $appointments = Appointment::with(['representative', 'doctor'])
    //         ->where('representative_id', $representativeId)
    //         ->whereRaw("
    //         TIMESTAMPDIFF(HOUR, CONCAT(date, ' ', start_time), NOW()) <= 48
    //     ") 
    //         ->whereRaw("
    //         TIMESTAMPDIFF(HOUR, CONCAT(date, ' ', start_time), NOW()) >= 0
    //     ") 
    //         ->orderBy('date', 'asc')
    //         ->orderBy('start_time', 'asc')
    //         ->get();

    //     if ($appointments->isNotEmpty()) {
    //         return ApiResponse::sendResponse(200, 'Appointments fetched successfully', AppointmentsResource::collection($appointments));
    //     }

    //     return ApiResponse::sendResponse(200, 'Appointments Not Found', []);
    // }

    private function refreshRepresentativeAppointments(AppointmentStatusRefreshService $statusRefresh): int
    {
        $representativeId = (int) auth()->id();
        $statusRefresh->refreshForRepresentative($representativeId);

        return $representativeId;
    }

    private function countUsedVisitsForDate(int $representativeId, string $targetDate): int
    {
        return Appointment::where('representative_id', $representativeId)
            ->whereDate('date', $targetDate)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
    }

    private function resolveRepresentativeDailyVisitsLimit(Representative $representative): int
    {
        return max(0, (int) ($representative->company->visits_per_day ?? 0));
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
