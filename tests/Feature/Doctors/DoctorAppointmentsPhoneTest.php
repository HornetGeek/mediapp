<?php

namespace Tests\Feature\Doctors;

use App\Events\SendNotificationEvent;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\Specialty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorAppointmentsPhoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    public function test_doctor_appointments_endpoint_returns_representative_phone(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this
            ->getJson('/api/doctor/doctor/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.phone', $rep->phone);
        $response->assertJsonMissing(['phone' => $doctor->phone]);
    }

    public function test_doctor_appointments_endpoint_filters_by_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'pending');
        $response->assertJsonPath('data.0.phone', $rep->phone);
    }

    public function test_doctor_appointments_endpoint_accepts_deleted_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        $deletedAppointment = Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '13:05:00',
            'status' => 'deleted',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=deleted');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $deletedAppointment->id);
        $response->assertJsonPath('data.0.status', 'deleted');
        $response->assertJsonPath('data.0.phone', $rep->phone);
    }

    public function test_doctor_appointments_endpoint_rejects_invalid_status_value(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=invalid_status');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
    }

    public function test_doctor_appointments_endpoint_rejects_invalid_pagination_values(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $invalidPageResponse = $this->getJson('/api/doctor/doctor/appointments?page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidPerPageResponse = $this->getJson('/api/doctor/doctor/appointments?per_page=101');
        $invalidPerPageResponse->assertStatus(422);
        $invalidPerPageResponse->assertJsonPath('code', 422);
    }

    public function test_doctor_appointments_endpoint_returns_pagination_metadata_and_honors_page_and_per_page(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('12:0%d:00', $offset),
                'end_time' => sprintf('12:0%d:00', $offset + 1),
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 4);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_doctor_appointments_endpoint_combines_status_filter_with_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('14:0%d:00', $offset),
                'end_time' => sprintf('14:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=confirmed&per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertJsonPath('data.1.status', 'confirmed');
    }

    public function test_doctor_appointments_endpoint_supports_search_by_representative_company_and_appointment_code(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        $primaryAppointment = Appointment::firstOrFail();

        $package = Package::firstOrFail();
        $searchCompany = Company::create([
            'name' => 'Search Company',
            'package_id' => $package->id,
            'phone' => '01222222223',
            'email' => 'search-company@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $searchRepresentative = Representative::create([
            'name' => 'Different Counterparty',
            'email' => 'search-rep@example.com',
            'phone' => '01033333335',
            'password' => 'secret123',
            'company_id' => $searchCompany->id,
            'status' => 'active',
        ]);

        $searchCompanyAppointment = Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $searchRepresentative->id,
            'company_id' => $searchCompany->id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $searchByRepresentative = $this->getJson('/api/doctor/doctor/appointments?search=Rep%20A');
        $searchByRepresentative->assertStatus(200);
        $this->assertSame(
            [$primaryAppointment->id],
            collect($searchByRepresentative->json('data'))->pluck('id')->values()->all()
        );

        $searchByCompany = $this->getJson('/api/doctor/doctor/appointments?search=Search%20Company');
        $searchByCompany->assertStatus(200);
        $this->assertSame(
            [$searchCompanyAppointment->id],
            collect($searchByCompany->json('data'))->pluck('id')->values()->all()
        );

        $searchByAppointmentCode = $this->getJson('/api/doctor/doctor/appointments?search=' . $primaryAppointment->appointment_code);
        $searchByAppointmentCode->assertStatus(200);
        $this->assertSame(
            [$primaryAppointment->id],
            collect($searchByAppointmentCode->json('data'))->pluck('id')->values()->all()
        );
    }

    public function test_doctor_appointments_endpoint_combines_status_search_and_pagination(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        $package = Package::firstOrFail();

        $matchedCompany = Company::create([
            'name' => 'Matched Company',
            'package_id' => $package->id,
            'phone' => '01222222224',
            'email' => 'matched-company@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $matchedRep = Representative::create([
            'name' => 'Matched Rep',
            'email' => 'matched-rep@example.com',
            'phone' => '01033333336',
            'password' => 'secret123',
            'company_id' => $matchedCompany->id,
            'status' => 'active',
        ]);

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $matchedRep->id,
                'company_id' => $matchedCompany->id,
                'date' => Carbon::now('Africa/Cairo')->addDays(3)->toDateString(),
                'start_time' => sprintf('16:0%d:00', $offset),
                'end_time' => sprintf('16:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        $otherCompany = Company::create([
            'name' => 'Other Company',
            'package_id' => $package->id,
            'phone' => '01222222225',
            'email' => 'other-company@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $otherRep = Representative::create([
            'name' => 'Other Rep',
            'email' => 'other-rep@example.com',
            'phone' => '01033333337',
            'password' => 'secret123',
            'company_id' => $otherCompany->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $otherRep->id,
            'company_id' => $otherCompany->id,
            'date' => Carbon::now('Africa/Cairo')->addDays(3)->toDateString(),
            'start_time' => '17:00:00',
            'end_time' => '17:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=confirmed&search=Matched&per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertJsonPath('data.0.representative.name', 'Matched Rep');
    }

    public function test_doctor_appointments_endpoint_returns_not_found_with_pagination_when_filter_is_empty(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments?status=left');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Appointments Not Found');
        $response->assertJsonPath('pagination.total', 0);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonCount(0, 'data');
    }

    public function test_doctor_status_endpoints_return_representative_phone_and_reps_endpoint_stays_unchanged(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        $pendingResponse = $this
            ->getJson('/api/doctor/appointments/pending');
        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('data.0.phone', $rep->phone);

        $appointment = Appointment::firstOrFail();
        $appointment->update(['status' => 'cancelled']);

        $cancelledResponse = $this
            ->getJson('/api/doctor/appointments/cancelled');
        $cancelledResponse->assertStatus(200);
        $cancelledResponse->assertJsonPath('data.0.phone', $rep->phone);

        $appointment->update(['status' => 'confirmed']);
        $confirmedResponse = $this
            ->getJson('/api/doctor/appointments/confirmed');
        $confirmedResponse->assertStatus(200);
        $confirmedResponse->assertJsonPath('data.0.phone', $rep->phone);

        Sanctum::actingAs($rep, ['representative']);
        $repsResponse = $this
            ->getJson('/api/reps/booked/appointments');
        $repsResponse->assertStatus(200);
        $repsResponse->assertJsonPath('data.0.phone', $doctor->phone);
    }

    public function test_doctor_cancelled_appointments_endpoint_supports_pagination_metadata(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('cancelled');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/appointments/cancelled?per_page=1&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
    }

    public function test_representative_appointments_by_status_endpoint_supports_pagination_metadata(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '13:00:00',
            'end_time' => '13:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/appointments/by-status?status=pending&per_page=1&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
    }

    public function test_representative_doctors_by_speciality_endpoint_supports_pagination_metadata(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Doctors::create([
            'name' => 'Doctor Pagination',
            'email' => 'doctor-pagination@example.com',
            'phone' => '01111111198',
            'password' => 'secret123',
            'specialty_id' => $doctor->specialty_id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/doctorsBySpeciality?specialty_id=' . $doctor->specialty_id . '&per_page=1&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
    }

    public function test_doctor_status_endpoints_validate_pagination_values(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($doctor, ['doctor']);

        foreach ([
            '/api/doctor/filter-appointment/reps',
            '/api/doctor/appointments/cancelled',
            '/api/doctor/appointments/pending',
            '/api/doctor/appointments/confirmed',
        ] as $endpoint) {
            $invalidPageResponse = $this->getJson($endpoint . '?page=0');
            $invalidPageResponse->assertStatus(422);
            $invalidPageResponse->assertJsonPath('code', 422);

            $invalidPerPageResponse = $this->getJson($endpoint . '?per_page=101');
            $invalidPerPageResponse->assertStatus(422);
            $invalidPerPageResponse->assertJsonPath('code', 422);
        }
    }

    public function test_representative_status_endpoints_validate_pagination_values(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        foreach ([
            '/api/reps/appointments/by-status',
            '/api/reps/appointments/cancelled',
            '/api/reps/appointments/pending',
            '/api/reps/appointments/confirmed',
            '/api/reps/appointments/lefting',
            '/api/reps/appointments/suspended',
            '/api/reps/appointments/filtration',
        ] as $endpoint) {
            $invalidPageResponse = $this->getJson($endpoint . '?page=0');
            $invalidPageResponse->assertStatus(422);
            $invalidPageResponse->assertJsonPath('code', 422);

            $invalidPerPageResponse = $this->getJson($endpoint . '?per_page=0');
            $invalidPerPageResponse->assertStatus(422);
            $invalidPerPageResponse->assertJsonPath('code', 422);
        }
    }

    public function test_representative_status_endpoints_support_pagination_metadata_for_each_status(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Appointment::query()->delete();

        $statusEndpoints = [
            'cancelled' => '/api/reps/appointments/cancelled',
            'pending' => '/api/reps/appointments/pending',
            'confirmed' => '/api/reps/appointments/confirmed',
            'left' => '/api/reps/appointments/lefting',
            'suspended' => '/api/reps/appointments/suspended',
        ];

        $baseDate = Carbon::now('Africa/Cairo')->addDays(3);
        foreach (array_keys($statusEndpoints) as $statusIndex => $status) {
            for ($offset = 0; $offset < 2; $offset++) {
                $startAt = $baseDate->copy()->addDays($statusIndex)->setTime(9 + $offset, 0, 0);
                $endAt = $startAt->copy()->addMinutes(5);

                Appointment::create([
                    'doctors_id' => $doctor->id,
                    'representative_id' => $rep->id,
                    'company_id' => $rep->company_id,
                    'date' => $startAt->toDateString(),
                    'start_time' => $startAt->format('H:i:s'),
                    'end_time' => $endAt->format('H:i:s'),
                    'status' => $status,
                ]);
            }
        }

        Sanctum::actingAs($rep, ['representative']);

        foreach ($statusEndpoints as $status => $endpoint) {
            $response = $this->getJson($endpoint . '?per_page=1&page=2');

            $response->assertStatus(200);
            $response->assertJsonPath('pagination.current_page', 2);
            $response->assertJsonPath('pagination.per_page', 1);
            $response->assertJsonPath('pagination.total', 2);
            $response->assertJsonPath('pagination.last_page', 2);
            $response->assertJsonCount(1, 'data');
            $response->assertJsonPath('data.0.status', $status);
        }

        $byStatusResponse = $this->getJson('/api/reps/appointments/by-status?status=confirmed&per_page=1&page=2');
        $byStatusResponse->assertStatus(200);
        $byStatusResponse->assertJsonPath('pagination.current_page', 2);
        $byStatusResponse->assertJsonPath('pagination.per_page', 1);
        $byStatusResponse->assertJsonPath('pagination.total', 2);
        $byStatusResponse->assertJsonPath('pagination.last_page', 2);
        $byStatusResponse->assertJsonCount(1, 'data');
        $byStatusResponse->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_representative_filter_appointments_endpoint_supports_pagination_metadata(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Appointment::query()->delete();

        foreach ([11, 12] as $hour) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(5)->toDateString(),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:05:00', $hour),
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/appointments/filtration?per_page=1&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
    }

    public function test_super_admin_doctors_endpoint_supports_pagination_and_validation(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('pending');

        $extraDoctor = Doctors::create([
            'name' => 'Doctor Super Admin Pagination',
            'email' => 'doctor-super-admin-pagination@example.com',
            'phone' => '01111111197',
            'password' => 'secret123',
            'specialty_id' => $doctor->specialty_id,
            'status' => 'active',
        ]);
        $this->createFullWeekAvailability($extraDoctor);

        $superAdmin = $this->createSuperAdminUser();
        Sanctum::actingAs($superAdmin, ['super-admin']);

        $response = $this->getJson('/api/super-admin/doctors?per_page=1&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 1);
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');

        $invalidPageResponse = $this->getJson('/api/super-admin/doctors?page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidPerPageResponse = $this->getJson('/api/super-admin/doctors?per_page=101');
        $invalidPerPageResponse->assertStatus(422);
        $invalidPerPageResponse->assertJsonPath('code', 422);
    }

    public function test_super_admin_visits_track_endpoints_support_pagination_and_validation(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'confirmed',
        ]);

        $superAdmin = $this->createSuperAdminUser();
        Sanctum::actingAs($superAdmin, ['super-admin']);

        $listResponse = $this->getJson('/api/super-admin/visits-track?per_page=1&page=2');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonPath('pagination.current_page', 2);
        $listResponse->assertJsonPath('pagination.per_page', 1);
        $listResponse->assertJsonPath('pagination.total', 2);
        $listResponse->assertJsonPath('pagination.last_page', 2);
        $listResponse->assertJsonCount(1, 'data');

        $filterResponse = $this->getJson('/api/super-admin/visits-track/filter?doctor_name=Doctor&per_page=1&page=1');
        $filterResponse->assertStatus(200);
        $filterResponse->assertJsonPath('pagination.current_page', 1);
        $filterResponse->assertJsonPath('pagination.per_page', 1);
        $this->assertGreaterThanOrEqual(1, (int) $filterResponse->json('pagination.total'));

        $invalidListPagination = $this->getJson('/api/super-admin/visits-track?page=0');
        $invalidListPagination->assertStatus(422);
        $invalidListPagination->assertJsonPath('code', 422);

        $invalidFilterPagination = $this->getJson('/api/super-admin/visits-track/filter?per_page=0');
        $invalidFilterPagination->assertStatus(422);
        $invalidFilterPagination->assertJsonPath('code', 422);
    }

    public function test_reps_booked_appointments_refreshes_pending_to_suspended_without_cron(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subMinutes(20);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'pending',
            'cancelled_by' => null,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id', $appointment->id);
        $response->assertJsonPath('data.0.status', 'suspended');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);
    }

    public function test_reps_booked_appointments_endpoint_filters_by_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=pending');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'pending');
        $response->assertJsonPath('data.0.phone', $doctor->phone);
    }

    public function test_reps_booked_appointments_endpoint_accepts_deleted_status_query_param(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        $deletedAppointment = Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'deleted',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=deleted');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $deletedAppointment->id);
        $response->assertJsonPath('data.0.status', 'deleted');
        $response->assertJsonPath('data.0.phone', $doctor->phone);
    }

    public function test_reps_booked_appointments_endpoint_rejects_invalid_status_value(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=invalid_status');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 422);
    }

    public function test_reps_booked_appointments_endpoint_rejects_invalid_pagination_values(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $invalidPageResponse = $this->getJson('/api/reps/booked/appointments?page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidPerPageResponse = $this->getJson('/api/reps/booked/appointments?per_page=0');
        $invalidPerPageResponse->assertStatus(422);
        $invalidPerPageResponse->assertJsonPath('code', 422);
    }

    public function test_reps_booked_appointments_endpoint_returns_pagination_metadata_and_honors_page_and_per_page(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('13:0%d:00', $offset),
                'end_time' => sprintf('13:0%d:00', $offset + 1),
                'status' => 'pending',
            ]);
        }

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 4);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_reps_booked_appointments_endpoint_combines_status_filter_with_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        foreach ([1, 2, 3] as $offset) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
                'start_time' => sprintf('15:0%d:00', $offset),
                'end_time' => sprintf('15:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=confirmed&per_page=2&page=1');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertJsonPath('data.1.status', 'confirmed');
    }

    public function test_reps_booked_appointments_endpoint_supports_search_by_doctor_company_and_appointment_code(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $firstAppointment = Appointment::firstOrFail();
        $specialty = Specialty::firstOrFail();

        $searchDoctor = Doctors::create([
            'name' => 'Doctor Search Target',
            'email' => 'doctor-search-target@example.com',
            'phone' => '01111111113',
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);

        $doctorAppointment = Appointment::create([
            'doctors_id' => $searchDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(2)->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $searchByDoctor = $this->getJson('/api/reps/booked/appointments?search=Search%20Target');
        $searchByDoctor->assertStatus(200);
        $this->assertSame(
            [$doctorAppointment->id],
            collect($searchByDoctor->json('data'))->pluck('id')->values()->all()
        );

        $searchByCompany = $this->getJson('/api/reps/booked/appointments?search=Company%20A');
        $searchByCompany->assertStatus(200);
        $searchByCompany->assertJsonPath('pagination.total', 2);

        $searchByAppointmentCode = $this->getJson('/api/reps/booked/appointments?search=' . $firstAppointment->appointment_code);
        $searchByAppointmentCode->assertStatus(200);
        $this->assertSame(
            [$firstAppointment->id],
            collect($searchByAppointmentCode->json('data'))->pluck('id')->values()->all()
        );
    }

    public function test_reps_booked_appointments_endpoint_supports_date_and_specialty_filters(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');

        $matchedSpecialty = Specialty::create(['name' => 'Internal Medicine']);
        $otherSpecialty = Specialty::create(['name' => 'Orthopedics']);
        $targetDate = Carbon::now('Africa/Cairo')->addDays(6)->toDateString();

        $matchedDoctor = Doctors::create([
            'name' => 'Doctor Date Specialty Match',
            'email' => 'doctor-date-specialty-match@example.com',
            'phone' => '01111111128',
            'password' => 'secret123',
            'specialty_id' => $matchedSpecialty->id,
            'status' => 'active',
        ]);

        $matchedAppointment = Appointment::create([
            'doctors_id' => $matchedDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $targetDate,
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'confirmed',
        ]);

        $otherSpecialtyDoctor = Doctors::create([
            'name' => 'Doctor Same Date Different Specialty',
            'email' => 'doctor-same-date-other-specialty@example.com',
            'phone' => '01111111129',
            'password' => 'secret123',
            'specialty_id' => $otherSpecialty->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $otherSpecialtyDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $targetDate,
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => 'confirmed',
        ]);

        Appointment::create([
            'doctors_id' => $matchedDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(7)->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?date=' . $targetDate . '&specialty=Internal&page=1&per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.total', 1);
        $response->assertJsonPath('data.0.id', $matchedAppointment->id);
        $response->assertJsonPath('data.0.date', $targetDate);
        $response->assertJsonPath('data.0.doctor.specialty.name', 'Internal Medicine');
    }

    public function test_reps_booked_appointments_endpoint_combines_status_search_and_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $specialty = Specialty::findOrFail($doctor->specialty_id);

        foreach ([1, 2, 3] as $offset) {
            $matchedDoctor = Doctors::create([
                'name' => 'Matched Doctor ' . $offset,
                'email' => 'matched-doctor-' . $offset . '@example.com',
                'phone' => '0111111112' . $offset,
                'password' => 'secret123',
                'specialty_id' => $specialty->id,
                'status' => 'active',
            ]);

            Appointment::create([
                'doctors_id' => $matchedDoctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(3)->toDateString(),
                'start_time' => sprintf('18:0%d:00', $offset),
                'end_time' => sprintf('18:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        $otherDoctor = Doctors::create([
            'name' => 'Other Doctor',
            'email' => 'other-doctor@example.com',
            'phone' => '01111111127',
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $otherDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDays(3)->toDateString(),
            'start_time' => '19:00:00',
            'end_time' => '19:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=confirmed&search=Matched&per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertStringContainsString('Matched Doctor', (string) data_get($response->json('data.0'), 'doctor.name'));
    }

    public function test_reps_booked_appointments_endpoint_combines_status_search_date_specialty_and_pagination(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $matchedSpecialty = Specialty::create(['name' => 'Matched Specialty']);
        $otherSpecialty = Specialty::create(['name' => 'Other Specialty']);
        $targetDate = Carbon::now('Africa/Cairo')->addDays(8)->toDateString();

        foreach ([1, 2, 3] as $offset) {
            $matchedDoctor = Doctors::create([
                'name' => 'Matched Doctor ' . $offset,
                'email' => 'rep-date-specialty-matched-' . $offset . '@example.com',
                'phone' => '0111111114' . $offset,
                'password' => 'secret123',
                'specialty_id' => $matchedSpecialty->id,
                'status' => 'active',
            ]);

            Appointment::create([
                'doctors_id' => $matchedDoctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => $targetDate,
                'start_time' => sprintf('18:0%d:00', $offset),
                'end_time' => sprintf('18:0%d:00', $offset + 1),
                'status' => 'confirmed',
            ]);
        }

        $otherDoctor = Doctors::create([
            'name' => 'Other Doctor',
            'email' => 'rep-date-specialty-other-doctor@example.com',
            'phone' => '01111111146',
            'password' => 'secret123',
            'specialty_id' => $matchedSpecialty->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $otherDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $targetDate,
            'start_time' => '19:00:00',
            'end_time' => '19:05:00',
            'status' => 'confirmed',
        ]);

        $wrongSpecialtyDoctor = Doctors::create([
            'name' => 'Matched Doctor Wrong Specialty',
            'email' => 'rep-date-specialty-wrong-specialty@example.com',
            'phone' => '01111111147',
            'password' => 'secret123',
            'specialty_id' => $otherSpecialty->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $wrongSpecialtyDoctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $targetDate,
            'start_time' => '20:00:00',
            'end_time' => '20:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=confirmed&search=Matched&date=' . $targetDate . '&specialty=Matched%20Specialty&per_page=2&page=2');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.current_page', 2);
        $response->assertJsonPath('pagination.per_page', 2);
        $response->assertJsonPath('pagination.total', 3);
        $response->assertJsonPath('pagination.last_page', 2);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'confirmed');
        $response->assertStringContainsString('Matched Doctor', (string) data_get($response->json('data.0'), 'doctor.name'));
        $response->assertJsonPath('data.0.doctor.specialty.name', 'Matched Specialty');
        $response->assertJsonPath('data.0.date', $targetDate);
    }

    public function test_reps_booked_appointments_endpoint_returns_not_found_with_pagination_when_filter_is_empty(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/booked/appointments?status=left');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Appointments Not Found');
        $response->assertJsonPath('pagination.total', 0);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonCount(0, 'data');
    }

    public function test_appointments_search_without_matches_returns_not_found_with_pagination(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Sanctum::actingAs($doctor, ['doctor']);
        $doctorResponse = $this->getJson('/api/doctor/doctor/appointments?search=missing-term');
        $doctorResponse->assertStatus(200);
        $doctorResponse->assertJsonPath('message', 'Appointments Not Found');
        $doctorResponse->assertJsonPath('pagination.total', 0);
        $doctorResponse->assertJsonPath('pagination.current_page', 1);
        $doctorResponse->assertJsonCount(0, 'data');

        Sanctum::actingAs($rep, ['representative']);
        $repResponse = $this->getJson('/api/reps/booked/appointments?search=missing-term');
        $repResponse->assertStatus(200);
        $repResponse->assertJsonPath('message', 'Appointments Not Found');
        $repResponse->assertJsonPath('pagination.total', 0);
        $repResponse->assertJsonPath('pagination.current_page', 1);
        $repResponse->assertJsonCount(0, 'data');
    }

    public function test_appointments_endpoints_reject_invalid_search_type(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');

        Sanctum::actingAs($doctor, ['doctor']);
        $doctorResponse = $this->getJson('/api/doctor/doctor/appointments?search[]=invalid');
        $doctorResponse->assertStatus(422);
        $doctorResponse->assertJsonPath('code', 422);

        Sanctum::actingAs($rep, ['representative']);
        $repResponse = $this->getJson('/api/reps/booked/appointments?search[]=invalid');
        $repResponse->assertStatus(422);
        $repResponse->assertJsonPath('code', 422);

        $repInvalidDateResponse = $this->getJson('/api/reps/booked/appointments?date=2026-13-40');
        $repInvalidDateResponse->assertStatus(422);
        $repInvalidDateResponse->assertJsonPath('code', 422);

        $repInvalidSpecialtyResponse = $this->getJson('/api/reps/booked/appointments?specialty[]=invalid');
        $repInvalidSpecialtyResponse->assertStatus(422);
        $repInvalidSpecialtyResponse->assertJsonPath('code', 422);
    }

    public function test_doctor_appointments_refreshes_suspended_to_left_without_cron(): void
    {
        [$doctor] = $this->seedDoctorAppointmentData('suspended');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subHours(49);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => null,
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor/appointments');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.id', $appointment->id);
        $response->assertJsonPath('data.0.status', 'left');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'left',
            'cancelled_by' => 'system',
        ]);
    }

    public function test_reps_lefting_endpoint_returns_left_appointments_only(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('left');
        $leftAppointment = Appointment::firstOrFail();

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
            'start_time' => '12:00:00',
            'end_time' => '12:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/appointments/lefting');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $leftAppointment->id);
        $response->assertJsonPath('data.0.status', 'left');
    }

    public function test_representative_cancel_suspended_appointment_updates_status_to_deleted(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subMinutes(30);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);

        Event::fake([SendNotificationEvent::class]);
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/cancel-appointment/' . $appointment->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $appointment->id);
        $response->assertJsonPath('data.status', 'deleted');
        $response->assertJsonPath('data.cancelled_by', 'Reps.' . $rep->name);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'deleted',
            'cancelled_by' => 'Reps.' . $rep->name,
        ]);
        Event::assertDispatched(SendNotificationEvent::class);
    }

    public function test_representative_change_status_confirms_suspended_after_end_time_on_same_day_within_48_hours(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subMinutes(30);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);

        Event::fake([SendNotificationEvent::class]);
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/appointment/change-status', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $appointment->id);
        $response->assertJsonPath('data.status', 'confirmed');
        $response->assertJsonPath('data.cancelled_by', null);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'cancelled_by' => null,
        ]);
        Event::assertNotDispatched(SendNotificationEvent::class);
    }

    public function test_representative_completed_endpoint_confirms_without_dispatching_notification(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        Event::fake([SendNotificationEvent::class]);
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/completed-appointment/' . $appointment->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $appointment->id);
        $response->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
        Event::assertNotDispatched(SendNotificationEvent::class);
    }

    public function test_representative_change_status_confirms_suspended_next_day_within_48_hours(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subHours(26);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/appointment/change-status', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $appointment->id);
        $response->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'cancelled_by' => null,
        ]);
    }

    public function test_representative_change_status_rejects_after_48_hours_from_start_time(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subHours(49);
        $endAt = $startAt->copy()->addMinutes(5);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'pending',
            'cancelled_by' => null,
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/appointment/change-status', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'You can\'t change status after 48 hours from the appointment start time');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);
    }

    public function test_representative_change_status_rejects_before_appointment_end_time(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        $nowInCairo = Carbon::now('Africa/Cairo')->startOfMinute();
        $startAt = $nowInCairo->copy()->subMinutes(2);
        $endAt = $nowInCairo->copy()->addMinutes(3);

        $appointment->update([
            'date' => $startAt->toDateString(),
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $endAt->format('H:i:s'),
            'status' => 'suspended',
            'cancelled_by' => 'system',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/appointment/change-status', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'You can\'t change the status before the appointment end time.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'suspended',
        ]);
    }

    public function test_representative_change_status_rejects_non_suspended_statuses(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Appointment::query()->delete();

        $statusToAppointmentId = [];
        foreach (['pending', 'cancelled', 'deleted', 'left'] as $index => $status) {
            $appointment = Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => Carbon::now('Africa/Cairo')->addDays(2 + $index)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '10:05:00',
                'status' => $status,
            ]);
            $statusToAppointmentId[$status] = $appointment->id;
        }

        Sanctum::actingAs($rep, ['representative']);

        foreach ($statusToAppointmentId as $status => $appointmentId) {
            $response = $this->putJson('/api/reps/appointment/change-status', [
                'appointment_id' => $appointmentId,
            ]);

            $response->assertStatus(409);
            $response->assertJsonPath('message', 'You can only change status for suspended appointments.');

            $this->assertDatabaseHas('appointments', [
                'id' => $appointmentId,
                'status' => $status,
            ]);
        }
    }

    public function test_representative_change_status_validates_appointment_id_input(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Sanctum::actingAs($rep, ['representative']);

        $missingAppointmentResponse = $this->putJson('/api/reps/appointment/change-status', []);
        $missingAppointmentResponse->assertStatus(422);
        $missingAppointmentResponse->assertJsonPath('code', 422);

        $invalidAppointmentResponse = $this->putJson('/api/reps/appointment/change-status', [
            'appointment_id' => 'not-an-integer',
        ]);
        $invalidAppointmentResponse->assertStatus(422);
        $invalidAppointmentResponse->assertJsonPath('code', 422);
    }

    public function test_representative_change_status_uses_start_time_anchor_for_48_hour_window_near_midnight(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 22, 0, 0, 'Africa/Cairo'));

        try {
            [, $rep] = $this->seedDoctorAppointmentData('pending');
            $appointment = Appointment::firstOrFail();

            $startAt = Carbon::create(2026, 4, 5, 23, 30, 0, 'Africa/Cairo');
            $endAt = $startAt->copy()->addMinutes(5);

            $appointment->update([
                'date' => $startAt->toDateString(),
                'start_time' => $startAt->format('H:i:s'),
                'end_time' => $endAt->format('H:i:s'),
                'status' => 'suspended',
                'cancelled_by' => 'system',
            ]);

            Sanctum::actingAs($rep, ['representative']);

            $response = $this->putJson('/api/reps/appointment/change-status', [
                'appointment_id' => $appointment->id,
            ]);

            $response->assertStatus(200);
            $response->assertJsonPath('data.id', $appointment->id);
            $response->assertJsonPath('data.status', 'confirmed');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_representative_cannot_cancel_left_appointment(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('left');
        $appointment = Appointment::firstOrFail();

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->putJson('/api/reps/cancel-appointment/' . $appointment->id);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'You can\'t cancel appointment in left status');
    }

    public function test_booking_requires_strict_date_and_rejects_rollover_values(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);
        Sanctum::actingAs($rep, ['representative']);

        $missingDateResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'start_time' => '10:00:00',
        ]);
        $missingDateResponse->assertStatus(422);
        $missingDateResponse->assertJsonPath('message', 'The date field is required.');

        $rolloverDateResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => '2026-02-31',
            'start_time' => '10:00:00',
        ]);
        $rolloverDateResponse->assertStatus(422);
        $rolloverDateResponse->assertJsonPath('message', 'date must be in Y-m-d format');

        $weekdayDateResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => 'sunday',
            'start_time' => '10:00:00',
        ]);
        $weekdayDateResponse->assertStatus(422);
        $weekdayDateResponse->assertJsonPath('message', 'date must be in Y-m-d format');
    }

    public function test_booking_rejects_same_day_past_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 10, 0, 0, 'Africa/Cairo'));

        try {
            [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
            Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

            Sanctum::actingAs($rep, ['representative']);

            $response = $this->postJson('/api/reps/booking', [
                'doctors_id' => $doctor->id,
                'date' => Carbon::now('Africa/Cairo')->toDateString(),
                'start_time' => '09:59:00',
            ]);

            $response->assertStatus(422);
            $response->assertJsonPath('message', 'Cannot book an appointment in the past');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_rejects_same_day_time_equal_to_now(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 10, 0, 0, 'Africa/Cairo'));

        try {
            [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
            Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

            Sanctum::actingAs($rep, ['representative']);

            $response = $this->postJson('/api/reps/booking', [
                'doctors_id' => $doctor->id,
                'date' => Carbon::now('Africa/Cairo')->toDateString(),
                'start_time' => '10:00:00',
            ]);

            $response->assertStatus(422);
            $response->assertJsonPath('message', 'Cannot book an appointment in the past');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_allows_same_day_future_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 10, 0, 0, 'Africa/Cairo'));

        try {
            [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
            Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

            $requestedDate = Carbon::now('Africa/Cairo')->toDateString();
            Sanctum::actingAs($rep, ['representative']);

            $response = $this->postJson('/api/reps/booking', [
                'doctors_id' => $doctor->id,
                'date' => $requestedDate,
                'start_time' => '10:01:00',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('appointments', [
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'date' => $requestedDate,
                'start_time' => '10:01:00',
                'status' => 'pending',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_refreshes_stale_pending_with_same_doctor_before_pending_guard(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 10, 0, 0, 'Africa/Cairo'));

        try {
            [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
            Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

            $staleAppointment = Appointment::firstOrFail();
            $staleAppointment->update([
                'date' => Carbon::now('Africa/Cairo')->subDay()->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '09:05:00',
                'status' => 'pending',
                'cancelled_by' => null,
            ]);

            $requestedDate = Carbon::now('Africa/Cairo')->toDateString();
            Sanctum::actingAs($rep, ['representative']);

            $response = $this->postJson('/api/reps/booking', [
                'doctors_id' => $doctor->id,
                'date' => $requestedDate,
                'start_time' => '10:01:00',
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('appointments', [
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'date' => $requestedDate,
                'start_time' => '10:01:00',
                'status' => 'pending',
            ]);
            $this->assertDatabaseHas('appointments', [
                'id' => $staleAppointment->id,
                'status' => 'suspended',
                'cancelled_by' => 'system',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_booking_accepts_supported_time_formats_and_normalizes_to_h_i_s(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        $requestedDate = Carbon::now('Africa/Cairo')->addDays(3)->toDateString();
        Sanctum::actingAs($rep, ['representative']);

        $amPmResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '09:00 AM',
        ]);
        $amPmResponse->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '09:00:00')
                ->where('status', 'pending')
                ->exists()
        );

        Appointment::query()
            ->where('doctors_id', $doctor->id)
            ->where('representative_id', $rep->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'cancelled_by' => 'system']);

        $hourMinuteResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00',
        ]);
        $hourMinuteResponse->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '10:00:00')
                ->where('status', 'pending')
                ->exists()
        );

        Appointment::query()
            ->where('doctors_id', $doctor->id)
            ->where('representative_id', $rep->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'cancelled_by' => 'system']);

        $hourMinuteSecondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '11:00:00',
        ]);
        $hourMinuteSecondResponse->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '11:00:00')
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_booking_rejects_24_hour_boundary_start_times(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(3)->toDateString();

        Sanctum::actingAs($rep, ['representative']);

        $firstResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '24:00',
        ]);
        $firstResponse->assertStatus(422);
        $firstResponse->assertJsonPath('message', 'start_time cannot use 24:* values');

        $secondResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '24:30',
        ]);
        $secondResponse->assertStatus(422);
        $secondResponse->assertJsonPath('message', 'start_time cannot use 24:* values');
    }

    public function test_booking_outside_availability_is_rejected(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        DoctorAvailability::query()->where('doctors_id', $doctor->id)->delete();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(4)->toDateString();
        $requestedWeekday = strtolower(Carbon::parse($requestedDate, 'Africa/Cairo')->format('l'));
        $this->createAvailability($doctor, $requestedWeekday, '09:00:00', '10:00:00');

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:30:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Requested time is outside doctor availability');
    }

    public function test_booking_inside_same_day_availability_succeeds(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        DoctorAvailability::query()->where('doctors_id', $doctor->id)->delete();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(4)->toDateString();
        $requestedWeekday = strtolower(Carbon::parse($requestedDate, 'Africa/Cairo')->format('l'));
        $this->createAvailability($doctor, $requestedWeekday, '09:00:00', '11:00:00');

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '10:00:00')
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_booking_inside_one_of_multiple_same_day_slots_succeeds(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        DoctorAvailability::query()->where('doctors_id', $doctor->id)->delete();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(4)->toDateString();
        $requestedWeekday = strtolower(Carbon::parse($requestedDate, 'Africa/Cairo')->format('l'));

        $this->createAvailability($doctor, $requestedWeekday, '09:00:00', '10:00:00');
        $this->createAvailability($doctor, $requestedWeekday, '14:00:00', '15:00:00');

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '14:30:00',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '14:30:00')
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_booking_in_gap_between_multiple_same_day_slots_is_rejected(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        DoctorAvailability::query()->where('doctors_id', $doctor->id)->delete();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(4)->toDateString();
        $requestedWeekday = strtolower(Carbon::parse($requestedDate, 'Africa/Cairo')->format('l'));

        $this->createAvailability($doctor, $requestedWeekday, '09:00:00', '10:00:00');
        $this->createAvailability($doctor, $requestedWeekday, '14:00:00', '15:00:00');

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '11:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Requested time is outside doctor availability');
    }

    public function test_booking_inside_overnight_availability_from_previous_day_succeeds(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('confirmed');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 10]);

        DoctorAvailability::query()->where('doctors_id', $doctor->id)->delete();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(4)->toDateString();
        $previousWeekday = strtolower(Carbon::parse($requestedDate, 'Africa/Cairo')->subDay()->format('l'));
        $this->createAvailability($doctor, $previousWeekday, '22:00:00', '02:00:00', true);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '01:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            Appointment::query()
                ->where('doctors_id', $doctor->id)
                ->where('representative_id', $rep->id)
                ->whereDate('date', $requestedDate)
                ->where('start_time', '01:00:00')
                ->where('status', 'pending')
                ->exists()
        );
    }

    public function test_booking_daily_limit_is_enforced_for_requested_date_not_today(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();

        Company::where('id', $rep->company_id)->update(['visits_per_day' => 1]);

        $today = Carbon::now('Africa/Cairo')->toDateString();
        $requestedDate = Carbon::now('Africa/Cairo')->addDays(2)->toDateString();

        $appointment->update([
            'date' => $today,
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'representative_id' => $rep->id,
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00:00',
            'status' => 'pending',
        ]);
    }

    public function test_booking_daily_limit_is_scoped_to_the_same_representative_only(): void
    {
        [$doctor, $repA] = $this->seedDoctorAppointmentData('pending');
        $appointment = Appointment::firstOrFail();
        $company = Company::findOrFail($repA->company_id);
        $company->update(['visits_per_day' => 1]);

        $repB = Representative::create([
            'name' => 'Rep B',
            'email' => 'rep-b-limit@example.com',
            'phone' => '01033333338',
            'password' => 'secret123',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $requestedDate = Carbon::now('Africa/Cairo')->addDays(2)->toDateString();
        $appointment->update([
            'representative_id' => $repA->id,
            'date' => $requestedDate,
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($repB, ['representative']);

        $response = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'representative_id' => $repB->id,
            'doctors_id' => $doctor->id,
            'date' => $requestedDate,
            'start_time' => '10:00:00',
            'status' => 'pending',
        ]);
    }

    public function test_reps_profile_returns_daily_visit_fields_and_counts_only_pending_and_confirmed_for_today(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        $company = Company::findOrFail($rep->company_id);
        $company->update(['visits_per_day' => 5]);

        $todayInCairo = Carbon::now('Africa/Cairo')->toDateString();
        $tomorrowInCairo = Carbon::now('Africa/Cairo')->addDay()->toDateString();
        $startAt = Carbon::now('Africa/Cairo')->addHour()->startOfMinute();

        $seededAppointment = Appointment::firstOrFail();
        $seededAppointment->update([
            'date' => $todayInCairo,
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addMinutes(5)->format('H:i:s'),
            'status' => 'pending',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $todayInCairo,
            'start_time' => $startAt->copy()->addMinutes(10)->format('H:i:s'),
            'end_time' => $startAt->copy()->addMinutes(15)->format('H:i:s'),
            'status' => 'confirmed',
        ]);

        foreach (['cancelled', 'suspended', 'left', 'deleted'] as $index => $status) {
            Appointment::create([
                'doctors_id' => $doctor->id,
                'representative_id' => $rep->id,
                'company_id' => $rep->company_id,
                'date' => $todayInCairo,
                'start_time' => $startAt->copy()->addMinutes(20 + ($index * 10))->format('H:i:s'),
                'end_time' => $startAt->copy()->addMinutes(25 + ($index * 10))->format('H:i:s'),
                'status' => $status,
            ]);
        }

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $rep->company_id,
            'date' => $tomorrowInCairo,
            'start_time' => '20:00:00',
            'end_time' => '20:05:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.daily_visits_limit', 5);
        $response->assertJsonPath('data.used_visits_today', 2);
        $response->assertJsonPath('data.remaining_visits_today', 3);
    }

    public function test_reps_profile_returns_zero_remaining_when_daily_limit_is_null(): void
    {
        [, $rep] = $this->seedDoctorAppointmentData('pending');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => null]);

        $todayInCairo = Carbon::now('Africa/Cairo')->toDateString();
        $startAt = Carbon::now('Africa/Cairo')->addHour()->startOfMinute();
        $appointment = Appointment::firstOrFail();

        $appointment->update([
            'date' => $todayInCairo,
            'start_time' => $startAt->format('H:i:s'),
            'end_time' => $startAt->copy()->addMinutes(5)->format('H:i:s'),
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/profile');

        $response->assertStatus(200);
        $response->assertJsonPath('data.daily_visits_limit', 0);
        $response->assertJsonPath('data.used_visits_today', 1);
        $response->assertJsonPath('data.remaining_visits_today', 0);
    }

    public function test_reps_profile_usage_matches_booking_guard_for_today(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('pending');
        Company::where('id', $rep->company_id)->update(['visits_per_day' => 1]);

        $todayInCairo = Carbon::now('Africa/Cairo')->toDateString();
        $appointment = Appointment::firstOrFail();

        $appointment->update([
            'date' => $todayInCairo,
            'start_time' => '09:00:00',
            'end_time' => '09:05:00',
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($rep, ['representative']);

        $profileResponse = $this->getJson('/api/reps/profile');
        $profileResponse->assertStatus(200);
        $profileResponse->assertJsonPath('data.daily_visits_limit', 1);
        $profileResponse->assertJsonPath('data.used_visits_today', 1);
        $profileResponse->assertJsonPath('data.remaining_visits_today', 0);

        $bookingResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $todayInCairo,
            'start_time' => '10:00:00',
        ]);

        $bookingResponse->assertStatus(403);
        $bookingResponse->assertJsonPath('message', 'You have reached the maximum number of appointments allowed for the selected date');
    }

    public function test_doctor_cancelled_endpoint_does_not_leak_other_doctors_appointments(): void
    {
        [$doctor, $rep] = $this->seedDoctorAppointmentData('cancelled');
        $ownAppointment = Appointment::firstOrFail();

        $otherSpecialty = Specialty::create(['name' => 'Neuro']);

        $otherDoctor = Doctors::create([
            'name' => 'Doctor B',
            'email' => 'doctor-b@example.com',
            'phone' => '01111111112',
            'password' => 'secret123',
            'specialty_id' => $otherSpecialty->id,
            'status' => 'active',
        ]);

        $otherRep = Representative::create([
            'name' => 'Rep B',
            'email' => 'rep-b@example.com',
            'phone' => '01033333334',
            'password' => 'secret123',
            'company_id' => $rep->company_id,
            'status' => 'active',
        ]);

        $otherAppointment = Appointment::create([
            'doctors_id' => $otherDoctor->id,
            'representative_id' => $otherRep->id,
            'company_id' => $rep->company_id,
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '11:05:00',
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/appointments/cancelled');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $doctorIds = collect($response->json('data'))->pluck('doctor.id')->unique()->values()->all();

        $this->assertContains($ownAppointment->id, $ids);
        $this->assertNotContains($otherAppointment->id, $ids);
        $this->assertSame([$doctor->id], $doctorIds);
    }

    /**
     * @return array{0: \App\Models\Doctors, 1: \App\Models\Representative}
     */
    private function seedDoctorAppointmentData(string $status): array
    {
        $specialty = Specialty::create(['name' => 'Cardio']);

        $doctor = Doctors::create([
            'name' => 'Doctor A',
            'email' => 'doctor-a@example.com',
            'phone' => '01111111111',
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);
        $this->createFullWeekAvailability($doctor);

        $package = Package::create([
            'name' => 'Quarterly',
            'price' => 1000,
            'duration' => 90,
            'plan_type' => 'quarterly',
            'billing_months' => 3,
        ]);

        $company = Company::create([
            'name' => 'Company A',
            'package_id' => $package->id,
            'phone' => '01222222222',
            'email' => 'company-a@example.com',
            'password' => 'secret123',
            'status' => 'active',
        ]);

        $rep = Representative::create([
            'name' => 'Rep A',
            'email' => 'rep-a@example.com',
            'phone' => '01033333333',
            'password' => 'secret123',
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $rep->id,
            'company_id' => $company->id,
            'date' => Carbon::now('Africa/Cairo')->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '10:05:00',
            'status' => $status,
        ]);

        return [$doctor, $rep];
    }

    private function createSuperAdminUser(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin-' . uniqid('', true) . '@example.com',
            'password' => 'secret123',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function createAvailability(
        Doctors $doctor,
        string $date,
        string $startTime,
        string $endTime,
        bool $endsNextDay = false,
        string $status = 'available',
        int $maxRepsPerHour = 2
    ): DoctorAvailability {
        return DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'ends_next_day' => $endsNextDay,
            'max_reps_per_hour' => $maxRepsPerHour,
            'status' => $status,
        ]);
    }

    private function createFullWeekAvailability(Doctors $doctor): void
    {
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $weekday) {
            $this->createAvailability($doctor, $weekday, '00:00:00', '23:59:00');
        }
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'users',
            'doctor_representative_favorite',
            'line_representative',
            'area_representative',
            'lines',
            'areas',
            'doctor_blocks',
            'doctor_availabilities',
            'appointments',
            'representatives',
            'companies',
            'packages',
            'doctors',
            'specialties',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->nullable();
            $table->string('email_feedback')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->enum('status', ['active', 'busy'])->default('active');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('duration');
            $table->string('plan_type')->default('custom_days');
            $table->unsignedTinyInteger('billing_months')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->integer('visits_per_day')->nullable();
            $table->integer('num_of_reps')->nullable();
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('fcm_token')->nullable();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('lines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('area_representative', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('representative_id');
        });

        Schema::create('line_representative', function (Blueprint $table) {
            $table->unsignedBigInteger('line_id');
            $table->unsignedBigInteger('representative_id');
        });

        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('blockable_id');
            $table->string('blockable_type');
            $table->timestamps();
        });

        Schema::create('doctor_representative_favorite', function (Blueprint $table) {
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->boolean('is_fav')->default(true);
        });

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedTinyInteger('max_reps_per_hour')->default(2);
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->date('date')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended', 'deleted'])->nullable();
            $table->uuid('appointment_code')->unique();
            $table->string('cancelled_by')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
