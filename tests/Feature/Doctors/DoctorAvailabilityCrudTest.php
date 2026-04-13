<?php

namespace Tests\Feature\Doctors;

use App\Http\Resources\ListDoctorsResource;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorAvailabilityCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestingSchema();
    }

    public function test_doctor_available_times_endpoint_returns_saved_available_rows(): void
    {
        $doctor = $this->createDoctor('doctor-available-times-1@example.com', '01111111231');
        Sanctum::actingAs($doctor, ['doctor']);

        $saveResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'saturday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);

        $saveResponse->assertStatus(200);

        $response = $this->getJson('/api/doctor/doctor-available-times');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.available_times');
        $response->assertJsonFragment([
            'date' => 'Saturday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
            'status' => 'available',
        ]);
    }

    public function test_doctor_available_times_endpoint_orders_slots_by_weekday_then_start_time(): void
    {
        $doctor = $this->createDoctor('doctor-available-times-order@example.com', '01111111255');
        $this->createAvailability($doctor, 'monday', '13:00:00', '14:00:00', 'available');
        $this->createAvailability($doctor, 'sunday', '23:00:00', '23:30:00', 'available');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $this->createAvailability($doctor, 'friday', '08:00:00', '09:00:00', 'available');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor-available-times');
        $response->assertStatus(200);

        $slots = $response->json('data.available_times');
        $this->assertCount(4, $slots);

        $this->assertSame('Sunday', $slots[0]['date']);
        $this->assertSame('11:00 PM', $slots[0]['start_time']);
        $this->assertSame('Monday', $slots[1]['date']);
        $this->assertSame('09:00 AM', $slots[1]['start_time']);
        $this->assertSame('Monday', $slots[2]['date']);
        $this->assertSame('01:00 PM', $slots[2]['start_time']);
        $this->assertSame('Friday', $slots[3]['date']);
        $this->assertSame('08:00 AM', $slots[3]['start_time']);
    }

    public function test_doctor_available_times_endpoint_excludes_non_available_rows(): void
    {
        $doctor = $this->createDoctor('doctor-available-times-2@example.com', '01111111232');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'booked');
        $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'busy');
        $this->createAvailability($doctor, 'wednesday', '11:00:00', '12:00:00', 'canceled');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->getJson('/api/doctor/doctor-available-times');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data.available_times');
    }

    public function test_save_available_time_ignores_inbound_status_and_persists_available(): void
    {
        $doctor = $this->createDoctor('doctor-save-status-override@example.com', '01111111233');
        Sanctum::actingAs($doctor, ['doctor']);

        $payloads = [
            ['date' => 'monday', 'status' => 'booked', 'start_time' => '09:00', 'end_time' => '10:00'],
            ['date' => 'tuesday', 'status' => 'busy', 'start_time' => '10:00', 'end_time' => '11:00'],
            ['date' => 'wednesday', 'status' => 'canceled', 'start_time' => '11:00', 'end_time' => '12:00'],
        ];

        foreach ($payloads as $payload) {
            $response = $this->putJson('/api/doctor/save-available-time', $payload);
            $response->assertStatus(200);

            $this->assertDatabaseHas('doctor_availabilities', [
                'doctors_id' => $doctor->id,
                'date' => $payload['date'],
                'start_time' => sprintf('%02d:00:00', (int) explode(':', $payload['start_time'])[0]),
                'end_time' => sprintf('%02d:00:00', (int) explode(':', $payload['end_time'])[0]),
                'status' => 'available',
            ]);
        }
    }

    public function test_save_available_time_accepts_max_reps_per_range_one_and_two(): void
    {
        $doctor = $this->createDoctor('doctor-save-max-reps-values@example.com', '01111111257');
        Sanctum::actingAs($doctor, ['doctor']);

        $firstResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'monday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
            'max_reps_per_range' => 1,
        ]);
        $firstResponse->assertStatus(200);

        $secondResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'tuesday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
            'max_reps_per_range' => 2,
        ]);
        $secondResponse->assertStatus(200);

        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'max_reps_per_range' => 1,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'max_reps_per_range' => 2,
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_rejects_invalid_max_reps_per_range_values(): void
    {
        $doctor = $this->createDoctor('doctor-save-max-reps-invalid@example.com', '01111111258');
        Sanctum::actingAs($doctor, ['doctor']);

        $zeroResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'monday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
            'max_reps_per_range' => 0,
        ]);
        $zeroResponse->assertStatus(422);

        $threeResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'monday',
            'start_time' => '10:00 AM',
            'end_time' => '11:00 AM',
            'max_reps_per_range' => 3,
        ]);
        $threeResponse->assertStatus(422);
    }

    public function test_save_available_time_defaults_max_reps_per_range_to_two_when_omitted(): void
    {
        $doctor = $this->createDoctor('doctor-save-max-reps-default@example.com', '01111111259');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'wednesday',
            'start_time' => '01:00 PM',
            'end_time' => '02:00 PM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.available_times.0.max_reps_per_range', 2);

        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'max_reps_per_range' => 2,
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_enforces_duration_based_upper_bound_for_max_reps_per_range(): void
    {
        $doctor = $this->createDoctor('doctor-save-max-reps-duration-bound@example.com', '01111111261');
        Sanctum::actingAs($doctor, ['doctor']);

        $validResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'thursday',
            'start_time' => '09:00 AM',
            'end_time' => '12:00 PM',
            'max_reps_per_range' => 6,
        ]);
        $validResponse->assertStatus(200);
        $validResponse->assertJsonPath('data.available_times.0.max_reps_per_range', 6);

        $invalidResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'friday',
            'start_time' => '09:00 AM',
            'end_time' => '12:00 PM',
            'max_reps_per_range' => 7,
        ]);
        $invalidResponse->assertStatus(422);
        $invalidResponse->assertJsonPath('message', 'max_reps_per_range must be between 1 and 6');
    }

    public function test_update_available_time_updates_max_reps_per_range_and_defaults_to_computed_max_when_omitted(): void
    {
        $doctor = $this->createDoctor('doctor-update-max-reps@example.com', '01111111260');
        $availability = $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available', false, 2);
        Sanctum::actingAs($doctor, ['doctor']);

        $updateWithOneResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => 'monday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
            'max_reps_per_range' => 1,
        ]);
        $updateWithOneResponse->assertStatus(200);
        $updateWithOneResponse->assertJsonPath('data.max_reps_per_range', 1);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'max_reps_per_range' => 1,
        ]);

        $updateWithoutFieldResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => 'monday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);
        $updateWithoutFieldResponse->assertStatus(200);
        $updateWithoutFieldResponse->assertJsonPath('data.max_reps_per_range', 2);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'max_reps_per_range' => 2,
        ]);
    }

    public function test_doctor_profile_and_available_times_endpoint_return_consistent_available_slots(): void
    {
        $doctor = $this->createDoctor('doctor-profile-consistency@example.com', '01111111234');
        $availableA = $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $availableB = $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'available');
        $this->createAvailability($doctor, 'wednesday', '12:00:00', '13:00:00', 'busy');
        $this->createAvailability($doctor, 'thursday', '13:00:00', '14:00:00', 'booked');

        Sanctum::actingAs($doctor, ['doctor']);

        $availableTimesResponse = $this->getJson('/api/doctor/doctor-available-times');
        $profileResponse = $this->getJson('/api/doctor/profile');

        $availableTimesResponse->assertStatus(200);
        $profileResponse->assertStatus(200);

        $endpointIds = collect($availableTimesResponse->json('data.available_times'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
        $profileIds = collect($profileResponse->json('data.available_times'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$availableA->id, $availableB->id], $endpointIds);
        $this->assertSame($endpointIds, $profileIds);
    }

    public function test_representative_doctor_profile_endpoint_returns_only_available_times(): void
    {
        $doctor = $this->createDoctor('rep-doctor-profile-filter@example.com', '01111111235');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'busy');
        $this->createAvailability($doctor, 'wednesday', '11:00:00', '12:00:00', 'booked');
        $this->createAvailability($doctor, 'thursday', '12:00:00', '13:00:00', 'canceled');
        $rep = $this->createRepresentative('rep-doctor-profile-filter@example.com', '01011111121');

        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/docotr/' . $doctor->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.available_times');
        $response->assertJsonPath('data.available_times.0.status', 'available');
        $response->assertJsonPath('data.available_times.0.date', 'Monday');
    }

    public function test_representative_doctor_listing_endpoints_return_only_available_times(): void
    {
        $doctor = $this->createDoctor('rep-doctor-list-filter@example.com', '01111111236');
        $doctor->update(['name' => 'Doctor Visible Slot']);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'busy');
        $this->createAvailability($doctor, 'wednesday', '11:00:00', '12:00:00', 'booked');
        $this->createAvailability($doctor, 'thursday', '12:00:00', '13:00:00', 'canceled');

        $rep = $this->createRepresentative('rep-doctor-list-filter@example.com', '01011111122');
        Sanctum::actingAs($rep, ['representative']);

        $allDoctorsResponse = $this->getJson('/api/reps/doctors/all');
        $allDoctorsResponse->assertStatus(200);
        $allDoctorsResponse->assertJsonPath('pagination.current_page', 1);
        $allDoctorsResponse->assertJsonPath('pagination.per_page', 10);
        $allDoctorsResponse->assertJsonPath('pagination.total', 1);
        $doctorPayload = collect($allDoctorsResponse->json('data'))
            ->firstWhere('id', $doctor->id);
        $this->assertNotNull($doctorPayload);
        $this->assertCount(1, $doctorPayload['available_times']);
        $this->assertSame('available', $doctorPayload['available_times'][0]['status']);
        $this->assertSame('Monday', $doctorPayload['available_times'][0]['date']);

        $searchResponse = $this->getJson('/api/reps/doctors/search?name=Visible');
        $searchResponse->assertStatus(200);
        $searchPayload = collect($searchResponse->json('data'))
            ->firstWhere('id', $doctor->id);
        $this->assertNotNull($searchPayload);
        $this->assertCount(1, $searchPayload['available_times']);
        $this->assertSame('available', $searchPayload['available_times'][0]['status']);
        $this->assertSame('Monday', $searchPayload['available_times'][0]['date']);

        $bySpecialityResponse = $this->getJson('/api/reps/doctorsBySpeciality?specialty_id=' . $doctor->specialty_id);
        $bySpecialityResponse->assertStatus(200);
        $specialityPayload = collect($bySpecialityResponse->json('data'))
            ->firstWhere('id', $doctor->id);
        $this->assertNotNull($specialityPayload);
        $this->assertCount(1, $specialityPayload['available_times']);
        $this->assertSame('available', $specialityPayload['available_times'][0]['status']);
        $this->assertSame('Monday', $specialityPayload['available_times'][0]['date']);
    }

    public function test_representative_doctors_all_supports_search_and_pagination(): void
    {
        $firstDoctor = $this->createDoctor('rep-all-search-one@example.com', '01111111280');
        $firstDoctor->update(['name' => 'Doctor Searchable One']);
        $this->createAvailability($firstDoctor, 'monday', '09:00:00', '10:00:00', 'available');

        $secondDoctor = $this->createDoctor('rep-all-search-two@example.com', '01111111281');
        $secondDoctor->update(['name' => 'Doctor Searchable Two']);
        $this->createAvailability($secondDoctor, 'monday', '10:00:00', '11:00:00', 'available');

        $thirdDoctor = $this->createDoctor('rep-all-search-three@example.com', '01111111282');
        $thirdDoctor->update(['name' => 'Doctor Hidden']);
        $this->createAvailability($thirdDoctor, 'monday', '11:00:00', '12:00:00', 'available');

        $rep = $this->createRepresentative('rep-all-search@example.com', '01011111180');
        Sanctum::actingAs($rep, ['representative']);

        $pageOneResponse = $this->getJson('/api/reps/doctors/all?search=Searchable&page=1&per_page=1');
        $pageOneResponse->assertStatus(200);
        $pageOneResponse->assertJsonPath('pagination.current_page', 1);
        $pageOneResponse->assertJsonPath('pagination.per_page', 1);
        $pageOneResponse->assertJsonPath('pagination.total', 2);
        $pageOneResponse->assertJsonPath('pagination.last_page', 2);
        $pageOneResponse->assertJsonCount(1, 'data');

        $pageTwoResponse = $this->getJson('/api/reps/doctors/all?search=Searchable&page=2&per_page=1');
        $pageTwoResponse->assertStatus(200);
        $pageTwoResponse->assertJsonPath('pagination.current_page', 2);
        $pageTwoResponse->assertJsonPath('pagination.per_page', 1);
        $pageTwoResponse->assertJsonPath('pagination.total', 2);
        $pageTwoResponse->assertJsonPath('pagination.last_page', 2);
        $pageTwoResponse->assertJsonCount(1, 'data');

        $pageOneId = (int) $pageOneResponse->json('data.0.id');
        $pageTwoId = (int) $pageTwoResponse->json('data.0.id');
        $this->assertNotSame($pageOneId, $pageTwoId);
        $this->assertTrue(in_array($pageOneId, [$firstDoctor->id, $secondDoctor->id], true));
        $this->assertTrue(in_array($pageTwoId, [$firstDoctor->id, $secondDoctor->id], true));
    }

    public function test_representative_doctors_all_search_matches_address_and_specialty_name(): void
    {
        $cardiology = Specialty::firstOrCreate(['name' => 'Cardiology']);
        $dermatology = Specialty::create(['name' => 'Dermatology']);

        $addressDoctor = $this->createDoctor('rep-all-address-search@example.com', '01111111284');
        $addressDoctor->update([
            'name' => 'Doctor Address Match',
            'address_1' => 'Alexandria Downtown',
            'specialty_id' => $cardiology->id,
        ]);
        $this->createAvailability($addressDoctor, 'monday', '09:00:00', '10:00:00', 'available');

        $specialtyDoctor = $this->createDoctor('rep-all-specialty-search@example.com', '01111111285');
        $specialtyDoctor->update([
            'name' => 'Doctor Specialty Match',
            'address_1' => 'Cairo Nasr City',
            'specialty_id' => $dermatology->id,
        ]);
        $this->createAvailability($specialtyDoctor, 'monday', '10:00:00', '11:00:00', 'available');

        $rep = $this->createRepresentative('rep-all-address-specialty-search@example.com', '01011111183');
        Sanctum::actingAs($rep, ['representative']);

        $addressSearchResponse = $this->getJson('/api/reps/doctors/all?search=Downtown&page=1&per_page=10');
        $addressSearchResponse->assertStatus(200);
        $addressSearchResponse->assertJsonPath('pagination.total', 1);
        $addressPayload = collect($addressSearchResponse->json('data'))->firstWhere('id', $addressDoctor->id);
        $this->assertNotNull($addressPayload);

        $specialtySearchResponse = $this->getJson('/api/reps/doctors/all?search=Dermatology&page=1&per_page=10');
        $specialtySearchResponse->assertStatus(200);
        $specialtySearchResponse->assertJsonPath('pagination.total', 1);
        $specialtyPayload = collect($specialtySearchResponse->json('data'))->firstWhere('id', $specialtyDoctor->id);
        $this->assertNotNull($specialtyPayload);
    }

    public function test_representative_doctors_all_supports_specialty_name_filter(): void
    {
        $internalMedicine = Specialty::firstOrCreate(['name' => 'Internal Medicine']);
        $orthopedics = Specialty::create(['name' => 'Orthopedics']);

        $internalDoctor = $this->createDoctor('rep-all-specialty-param-internal@example.com', '01111111289');
        $internalDoctor->update([
            'name' => 'Doctor Internal',
            'specialty_id' => $internalMedicine->id,
        ]);
        $this->createAvailability($internalDoctor, 'monday', '09:00:00', '10:00:00', 'available');

        $otherDoctor = $this->createDoctor('rep-all-specialty-param-other@example.com', '01111111290');
        $otherDoctor->update([
            'name' => 'Doctor Orthopedics',
            'specialty_id' => $orthopedics->id,
        ]);
        $this->createAvailability($otherDoctor, 'monday', '10:00:00', '11:00:00', 'available');

        $rep = $this->createRepresentative('rep-all-specialty-param@example.com', '01011111185');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/doctors/all?specialty=Internal%20Medicine&page=1&per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('pagination.total', 1);
        $response->assertJsonPath('data.0.id', $internalDoctor->id);
    }

    public function test_representative_doctors_all_supports_specialty_and_address_filters_with_combined_narrowing(): void
    {
        $cardiology = Specialty::firstOrCreate(['name' => 'Cardiology']);
        $neurology = Specialty::create(['name' => 'Neurology']);

        $targetDoctor = $this->createDoctor('rep-all-filter-target@example.com', '01111111286');
        $targetDoctor->update([
            'name' => 'Doctor Filter Target',
            'address_1' => 'Nasr City',
            'specialty_id' => $cardiology->id,
        ]);
        $this->createAvailability($targetDoctor, 'monday', '09:00:00', '10:00:00', 'available');

        $sameSpecialtyDifferentAddress = $this->createDoctor('rep-all-filter-same-specialty@example.com', '01111111287');
        $sameSpecialtyDifferentAddress->update([
            'name' => 'Doctor Cardiology Other Area',
            'address_1' => 'Maadi',
            'specialty_id' => $cardiology->id,
        ]);
        $this->createAvailability($sameSpecialtyDifferentAddress, 'monday', '10:00:00', '11:00:00', 'available');

        $sameAddressDifferentSpecialty = $this->createDoctor('rep-all-filter-same-address@example.com', '01111111288');
        $sameAddressDifferentSpecialty->update([
            'name' => 'Doctor Nasr Neurology',
            'address_1' => 'Nasr City',
            'specialty_id' => $neurology->id,
        ]);
        $this->createAvailability($sameAddressDifferentSpecialty, 'monday', '11:00:00', '12:00:00', 'available');

        $rep = $this->createRepresentative('rep-all-filter-combined@example.com', '01011111184');
        Sanctum::actingAs($rep, ['representative']);

        $specialtyOnlyResponse = $this->getJson('/api/reps/doctors/all?specialty_id=' . $cardiology->id . '&page=1&per_page=10');
        $specialtyOnlyResponse->assertStatus(200);
        $specialtyOnlyResponse->assertJsonPath('pagination.total', 2);

        $addressOnlyResponse = $this->getJson('/api/reps/doctors/all?address_1=Nasr&page=1&per_page=10');
        $addressOnlyResponse->assertStatus(200);
        $addressOnlyResponse->assertJsonPath('pagination.total', 2);

        $specialtyNameOnlyResponse = $this->getJson('/api/reps/doctors/all?specialty=Cardio&page=1&per_page=10');
        $specialtyNameOnlyResponse->assertStatus(200);
        $specialtyNameOnlyResponse->assertJsonPath('pagination.total', 2);

        $combinedResponse = $this->getJson('/api/reps/doctors/all?search=Target&specialty=Cardio&specialty_id=' . $cardiology->id . '&address_1=Nasr&page=1&per_page=1');
        $combinedResponse->assertStatus(200);
        $combinedResponse->assertJsonPath('pagination.total', 1);
        $combinedResponse->assertJsonPath('pagination.current_page', 1);
        $combinedResponse->assertJsonPath('pagination.per_page', 1);
        $combinedResponse->assertJsonPath('data.0.id', $targetDoctor->id);
    }

    public function test_representative_doctors_all_returns_empty_with_pagination_when_search_has_no_matches(): void
    {
        $doctor = $this->createDoctor('rep-all-empty@example.com', '01111111283');
        $doctor->update(['name' => 'Doctor Existing']);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');

        $rep = $this->createRepresentative('rep-all-empty@example.com', '01011111181');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/doctors/all?search=DoesNotExist&page=1&per_page=5');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Doctors Not Found');
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('pagination.current_page', 1);
        $response->assertJsonPath('pagination.per_page', 5);
        $response->assertJsonPath('pagination.total', 0);
        $response->assertJsonPath('pagination.last_page', 1);
    }

    public function test_representative_doctors_all_validates_search_and_pagination_params(): void
    {
        $rep = $this->createRepresentative('rep-all-validation@example.com', '01011111182');
        Sanctum::actingAs($rep, ['representative']);

        $invalidSearchResponse = $this->getJson('/api/reps/doctors/all?search[]=x');
        $invalidSearchResponse->assertStatus(422);
        $invalidSearchResponse->assertJsonPath('code', 422);

        $invalidSpecialtyNameResponse = $this->getJson('/api/reps/doctors/all?specialty[]=cardio');
        $invalidSpecialtyNameResponse->assertStatus(422);
        $invalidSpecialtyNameResponse->assertJsonPath('code', 422);

        $invalidPageResponse = $this->getJson('/api/reps/doctors/all?page=0&per_page=0');
        $invalidPageResponse->assertStatus(422);
        $invalidPageResponse->assertJsonPath('code', 422);

        $invalidSpecialtyResponse = $this->getJson('/api/reps/doctors/all?specialty_id=999999');
        $invalidSpecialtyResponse->assertStatus(422);
        $invalidSpecialtyResponse->assertJsonPath('code', 422);

        $invalidAddressResponse = $this->getJson('/api/reps/doctors/all?address_1[]=cairo');
        $invalidAddressResponse->assertStatus(422);
        $invalidAddressResponse->assertJsonPath('code', 422);
    }

    public function test_representative_favorite_doctor_endpoints_return_only_available_times(): void
    {
        $doctor = $this->createDoctor('rep-favorite-filter@example.com', '01111111237');
        $doctor->update(['name' => 'Favorite Filter Doctor']);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'busy');
        $this->createAvailability($doctor, 'wednesday', '11:00:00', '12:00:00', 'booked');
        $this->createAvailability($doctor, 'thursday', '12:00:00', '13:00:00', 'canceled');
        $rep = $this->createRepresentative('rep-favorite-filter@example.com', '01011111123');
        $rep->favoriteDoctors()->attach($doctor->id, ['is_fav' => true]);

        Sanctum::actingAs($rep, ['representative']);

        $listResponse = $this->getJson('/api/reps/favorite-doctors');
        $listResponse->assertStatus(200);
        $listPayload = collect($listResponse->json('data'))
            ->firstWhere('id', $doctor->id);
        $this->assertNotNull($listPayload);
        $this->assertCount(1, $listPayload['available_times']);
        $this->assertSame('available', $listPayload['available_times'][0]['status']);
        $this->assertSame('Monday', $listPayload['available_times'][0]['date']);

        $searchResponse = $this->getJson('/api/reps/search/favorite-doctors?search=Favorite');
        $searchResponse->assertStatus(200);
        $searchPayload = collect($searchResponse->json('data'))
            ->firstWhere('id', $doctor->id);
        $this->assertNotNull($searchPayload);
        $this->assertCount(1, $searchPayload['available_times']);
        $this->assertSame('available', $searchPayload['available_times'][0]['status']);
        $this->assertSame('Monday', $searchPayload['available_times'][0]['date']);
    }

    public function test_rep_doctor_endpoints_expose_busy_period_and_keep_available_times_when_doctor_is_currently_busy(): void
    {
        $busyDate = Carbon::now('Africa/Cairo')->toDateString();
        $doctor = $this->createDoctor('rep-busy-now@example.com', '01111111240');
        $doctor->update([
            'name' => 'Doctor Busy Now',
            'status' => 'busy',
            'from_date' => $busyDate,
            'to_date' => $busyDate,
        ]);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');

        $rep = $this->createRepresentative('rep-busy-now@example.com', '01011111124');
        $rep->favoriteDoctors()->attach($doctor->id, ['is_fav' => true]);
        Sanctum::actingAs($rep, ['representative']);

        $allDoctorsResponse = $this->getJson('/api/reps/doctors/all');
        $allDoctorsResponse->assertStatus(200);
        $doctorPayload = collect($allDoctorsResponse->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($doctorPayload);
        $this->assertBusyDoctorPayload($doctorPayload, $busyDate, $busyDate, 1);

        $searchResponse = $this->getJson('/api/reps/doctors/search?name=Busy');
        $searchResponse->assertStatus(200);
        $searchPayload = collect($searchResponse->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($searchPayload);
        $this->assertBusyDoctorPayload($searchPayload, $busyDate, $busyDate, 1);

        $profileResponse = $this->getJson('/api/reps/docotr/' . $doctor->id);
        $profileResponse->assertStatus(200);
        $this->assertBusyDoctorPayload($profileResponse->json('data'), $busyDate, $busyDate, 1);

        $bySpecialityResponse = $this->getJson('/api/reps/doctorsBySpeciality?specialty_id=' . $doctor->specialty_id);
        $bySpecialityResponse->assertStatus(200);
        $specialityPayload = collect($bySpecialityResponse->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($specialityPayload);
        $this->assertBusyDoctorPayload($specialityPayload, $busyDate, $busyDate, 1);

        $favoritesResponse = $this->getJson('/api/reps/favorite-doctors');
        $favoritesResponse->assertStatus(200);
        $favoritePayload = collect($favoritesResponse->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($favoritePayload);
        $this->assertBusyDoctorPayload($favoritePayload, $busyDate, $busyDate, 1);

        $favoritesSearchResponse = $this->getJson('/api/reps/search/favorite-doctors?search=Busy');
        $favoritesSearchResponse->assertStatus(200);
        $favoriteSearchPayload = collect($favoritesSearchResponse->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($favoriteSearchPayload);
        $this->assertBusyDoctorPayload($favoriteSearchPayload, $busyDate, $busyDate, 1);
    }

    public function test_doctor_endpoints_expose_busy_period_and_keep_available_times_when_doctor_is_currently_busy(): void
    {
        $busyDate = Carbon::now('Africa/Cairo')->toDateString();
        $doctor = $this->createDoctor('doctor-busy-self@example.com', '01111111270');
        $doctor->update([
            'status' => 'busy',
            'from_date' => $busyDate,
            'to_date' => $busyDate,
        ]);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');

        Sanctum::actingAs($doctor, ['doctor']);

        $availableTimesResponse = $this->getJson('/api/doctor/doctor-available-times');
        $availableTimesResponse->assertStatus(200);
        $this->assertBusyDoctorPayload($availableTimesResponse->json('data'), $busyDate, $busyDate, 1);

        $profileResponse = $this->getJson('/api/doctor/profile');
        $profileResponse->assertStatus(200);
        $this->assertBusyDoctorPayload($profileResponse->json('data'), $busyDate, $busyDate, 1);
    }

    public function test_representative_doctor_listing_returns_null_busy_period_for_non_busy_doctor(): void
    {
        $doctor = $this->createDoctor('rep-not-busy@example.com', '01111111241');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $rep = $this->createRepresentative('rep-not-busy@example.com', '01011111125');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/doctors/all');
        $response->assertStatus(200);

        $doctorPayload = collect($response->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($doctorPayload);
        $this->assertSame('active', $doctorPayload['status']);
        $this->assertNull($doctorPayload['busy_period']);
        $this->assertCount(1, $doctorPayload['available_times']);
        $this->assertSame('available', $doctorPayload['available_times'][0]['status']);
    }

    public function test_representative_booking_is_blocked_inside_busy_window_and_allowed_outside_it(): void
    {
        $busyDate = Carbon::now('Africa/Cairo')->toDateString();
        $outsideDate = Carbon::now('Africa/Cairo')->addDay()->toDateString();

        $doctor = $this->createDoctor('rep-book-busy@example.com', '01111111242');
        $doctor->update([
            'status' => 'busy',
            'from_date' => $busyDate,
            'to_date' => $busyDate,
        ]);
        $this->createAvailability(
            $doctor,
            strtolower(Carbon::parse($outsideDate)->format('l')),
            '09:00:00',
            '11:00:00',
            'available'
        );

        $rep = $this->createRepresentative('rep-book-busy@example.com', '01011111126');
        $rep->company()->update(['visits_per_day' => 5]);
        Sanctum::actingAs($rep, ['representative']);

        $blockedResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $busyDate,
            'start_time' => '09:00 AM',
        ]);
        $blockedResponse->assertStatus(403);
        $blockedResponse->assertJsonPath('message', 'Doctor is busy during the selected period');

        $allowedResponse = $this->postJson('/api/reps/booking', [
            'doctors_id' => $doctor->id,
            'date' => $outsideDate,
            'start_time' => '10:00 AM',
        ]);
        $allowedResponse->assertStatus(201);
        $allowedResponse->assertJsonPath('data.status', 'pending');
    }

    public function test_representative_doctor_listing_realtime_normalizes_expired_busy_status(): void
    {
        $yesterday = Carbon::now('Africa/Cairo')->subDay()->toDateString();
        $twoDaysAgo = Carbon::now('Africa/Cairo')->subDays(2)->toDateString();

        $doctor = $this->createDoctor('rep-expired-busy@example.com', '01111111243');
        $doctor->update([
            'status' => 'busy',
            'from_date' => $twoDaysAgo,
            'to_date' => $yesterday,
        ]);
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');

        $rep = $this->createRepresentative('rep-expired-busy@example.com', '01011111127');
        Sanctum::actingAs($rep, ['representative']);

        $response = $this->getJson('/api/reps/doctors/all');
        $response->assertStatus(200);

        $doctorPayload = collect($response->json('data'))->firstWhere('id', $doctor->id);
        $this->assertNotNull($doctorPayload);
        $this->assertSame('active', $doctorPayload['status']);
        $this->assertNull($doctorPayload['from_date']);
        $this->assertNull($doctorPayload['to_date']);
        $this->assertNull($doctorPayload['busy_period']);
        $this->assertCount(1, $doctorPayload['available_times']);

        $doctor->refresh();
        $this->assertSame('active', $doctor->status);
        $this->assertNull($doctor->from_date);
        $this->assertNull($doctor->to_date);
    }

    public function test_doctor_edit_status_requires_busy_dates_and_valid_date_order(): void
    {
        $doctor = $this->createDoctor('doctor-edit-status-validation@example.com', '01111111244');
        Sanctum::actingAs($doctor, ['doctor']);

        $missingDatesResponse = $this->putJson('/api/doctor/edit-status', [
            'status' => 'busy',
        ]);
        $missingDatesResponse->assertStatus(422);
        $missingDatesResponse->assertJsonPath('message', 'from_date and to_date are required when status is busy');

        $invalidOrderResponse = $this->putJson('/api/doctor/edit-status', [
            'status' => 'busy',
            'from_date' => '2026-04-20',
            'to_date' => '2026-04-19',
        ]);
        $invalidOrderResponse->assertStatus(422);
        $invalidOrderResponse->assertJsonPath('message', 'from_date must be before or equal to to_date');
    }

    public function test_doctor_edit_status_active_clears_busy_dates_and_busy_period(): void
    {
        $doctor = $this->createDoctor('doctor-edit-status-clear@example.com', '01111111245');
        $doctor->update([
            'status' => 'busy',
            'from_date' => '2026-04-20',
            'to_date' => '2026-04-22',
        ]);
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/edit-status', [
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.from_date', null);
        $response->assertJsonPath('data.to_date', null);
        $response->assertJsonPath('data.busy_period', null);

        $doctor->refresh();
        $this->assertSame('active', $doctor->status);
        $this->assertNull($doctor->from_date);
        $this->assertNull($doctor->to_date);
    }

    public function test_doctor_resource_based_edit_endpoints_return_only_available_times(): void
    {
        $doctor = $this->createDoctor('doctor-resource-filter@example.com', '01111111238');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');
        $this->createAvailability($doctor, 'tuesday', '10:00:00', '11:00:00', 'busy');
        $this->createAvailability($doctor, 'wednesday', '11:00:00', '12:00:00', 'booked');
        $this->createAvailability($doctor, 'thursday', '12:00:00', '13:00:00', 'canceled');

        Sanctum::actingAs($doctor, ['doctor']);

        $editProfileResponse = $this->putJson('/api/doctor/edit-profile', [
            'name' => 'Doctor Resource Filter Updated',
        ]);
        $editProfileResponse->assertStatus(200);
        $editProfileResponse->assertJsonCount(1, 'data.available_times');
        $editProfileResponse->assertJsonPath('data.available_times.0.status', 'available');
        $editProfileResponse->assertJsonPath('data.available_times.0.date', 'Monday');

        $editStatusResponse = $this->putJson('/api/doctor/edit-status', [
            'status' => 'active',
        ]);
        $editStatusResponse->assertStatus(200);
        $editStatusResponse->assertJsonCount(1, 'data.available_times');
        $editStatusResponse->assertJsonPath('data.available_times.0.status', 'available');
        $editStatusResponse->assertJsonPath('data.available_times.0.date', 'Monday');
    }

    public function test_doctor_can_update_own_available_time(): void
    {
        $doctor = $this->createDoctor('doctor-update-1@example.com', '01111111191');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => 'monday',
            'start_time' => '11:00 AM',
            'end_time' => '12:00 PM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $availability->id);
        $response->assertJsonPath('data.date', 'Monday');
        $response->assertJsonPath('data.start_time', '11:00 AM');
        $response->assertJsonPath('data.end_time', '12:00 PM');

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'doctors_id' => $doctor->id,
            'date' => 'monday',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    public function test_doctor_can_update_existing_available_time_with_same_values(): void
    {
        $doctor = $this->createDoctor('doctor-update-2@example.com', '01111111192');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $availability->id);
        $response->assertJsonPath('data.start_time', '09:00 AM');
        $response->assertJsonPath('data.end_time', '10:00 AM');
    }

    public function test_doctor_can_update_and_save_available_time_with_24_hour_input(): void
    {
        $doctor = $this->createDoctor('doctor-update-24h@example.com', '01111111210');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $updateResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '13:00',
            'end_time' => '14:00',
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.start_time', '01:00 PM');
        $updateResponse->assertJsonPath('data.end_time', '02:00 PM');
        $updateResponse->assertJsonPath('data.ends_next_day', false);

        $saveResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-21',
            'start_time' => '15:30',
            'end_time' => '16:30:00',
        ]);

        $saveResponse->assertStatus(200);
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'tuesday',
            'start_time' => '15:30:00',
            'end_time' => '16:30:00',
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_accepts_2400_end_time_and_normalizes_to_2359(): void
    {
        $doctor = $this->createDoctor('doctor-save-2400@example.com', '01111111246');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'sunday',
            'start_time' => '00:00',
            'end_time' => '24:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.available_times.0.start_time', '12:00 AM');
        $response->assertJsonPath('data.available_times.0.end_time', '11:59 PM');
        $response->assertJsonPath('data.available_times.0.ends_next_day', false);

        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'sunday',
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'ends_next_day' => 0,
            'status' => 'available',
        ]);
    }

    public function test_update_available_time_accepts_240000_end_time_and_normalizes_to_2359(): void
    {
        $doctor = $this->createDoctor('doctor-update-2400@example.com', '01111111247');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => 'sunday',
            'start_time' => '00:00',
            'end_time' => '24:00:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.start_time', '12:00 AM');
        $response->assertJsonPath('data.end_time', '11:59 PM');
        $response->assertJsonPath('data.ends_next_day', false);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'doctors_id' => $doctor->id,
            'date' => 'sunday',
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'ends_next_day' => 0,
        ]);
    }

    public function test_doctor_can_update_overnight_available_time_with_ends_next_day_flag(): void
    {
        $doctor = $this->createDoctor('doctor-update-overnight@example.com', '01111111216');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.start_time', '10:00 PM');
        $response->assertJsonPath('data.end_time', '02:00 AM');
        $response->assertJsonPath('data.ends_next_day', true);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'date' => 'monday',
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
            'ends_next_day' => 1,
        ]);
    }

    public function test_doctor_update_available_time_is_rejected_on_overlap_with_another_availability(): void
    {
        $doctor = $this->createDoctor('doctor-update-3@example.com', '01111111193');
        $first = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $this->createAvailability($doctor, '2026-04-20', '10:00:00', '11:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $first->id, [
            'date' => '2026-04-20',
            'start_time' => '09:30 AM',
            'end_time' => '10:30 AM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This time conflicts with an existing availability');
    }

    public function test_doctor_update_available_time_allows_move_to_weekday_with_non_overlapping_existing_slot(): void
    {
        $doctor = $this->createDoctor('doctor-update-weekday-conflict@example.com', '01111111225');
        $mondaySlot = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $tuesdaySlot = $this->createAvailability($doctor, '2026-04-21', '11:00:00', '12:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $tuesdaySlot->id, [
            'date' => 'monday',
            'start_time' => '11:00 AM',
            'end_time' => '12:00 PM',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $mondaySlot->id,
            'date' => '2026-04-20',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $tuesdaySlot->id,
            'date' => 'monday',
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'status' => 'available',
        ]);
    }

    public function test_doctor_update_available_time_ignores_overlap_with_non_available_slots(): void
    {
        $doctor = $this->createDoctor('doctor-update-status-scope@example.com', '01111111211');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $this->createAvailability($doctor, '2026-04-20', '09:30:00', '10:30:00', 'busy');
        $this->createAvailability($doctor, '2026-04-20', '09:45:00', '10:45:00', 'booked');
        $this->createAvailability($doctor, '2026-04-20', '08:45:00', '09:45:00', 'canceled');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '09:35 AM',
            'end_time' => '10:35 AM',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'start_time' => '09:35:00',
            'end_time' => '10:35:00',
        ]);
    }

    public function test_doctor_update_available_time_with_same_weekday_non_overlapping_rows_keeps_both_available(): void
    {
        $doctor = $this->createDoctor('doctor-update-duplicate-weekday@example.com', '01111111240');
        $targetAvailability = $this->createAvailability($doctor, 'thursday', '09:00:00', '10:00:00');
        $anotherAvailability = $this->createAvailability($doctor, '2026-04-23', '10:30:00', '11:30:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $targetAvailability->id, [
            'date' => 'thursday',
            'start_time' => '08:30 AM',
            'end_time' => '09:30 AM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'date' => 'Thursday',
            'start_time' => '08:30 AM',
            'end_time' => '09:30 AM',
            'status' => 'available',
        ]);

        $targetAvailability->refresh();
        $anotherAvailability->refresh();

        $this->assertSame('available', $targetAvailability->status);
        $this->assertSame('thursday', $targetAvailability->date);
        $this->assertSame('08:30:00', $targetAvailability->start_time);
        $this->assertSame('09:30:00', $targetAvailability->end_time);
        $this->assertSame('available', $anotherAvailability->status);

        $this->assertSame(
            2,
            DoctorAvailability::query()
                ->where('doctors_id', $doctor->id)
                ->where('status', 'available')
                ->where('date', 'thursday')
                ->count()
        );
    }

    public function test_doctor_update_available_time_allows_no_op_when_active_appointment_overlaps(): void
    {
        $doctor = $this->createDoctor('doctor-update-no-op@example.com', '01111111212');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $representative = $this->createRepresentative('rep-no-op@example.com', '01011111112');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $representative->id,
            'company_id' => $representative->company_id,
            'date' => '2026-04-20',
            'start_time' => '09:30:00',
            'end_time' => '09:35:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.start_time', '09:00 AM');
        $response->assertJsonPath('data.end_time', '10:00 AM');
    }

    public function test_doctor_update_available_time_is_rejected_when_real_change_overlaps_active_appointment(): void
    {
        $doctor = $this->createDoctor('doctor-update-appointment-overlap@example.com', '01111111213');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $representative = $this->createRepresentative('rep-overlap@example.com', '01011111113');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $representative->id,
            'company_id' => $representative->company_id,
            'date' => '2026-04-20',
            'start_time' => '09:30:00',
            'end_time' => '09:35:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '09:20 AM',
            'end_time' => '10:20 AM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot update availability that overlaps active appointments');
    }

    public function test_doctor_update_overnight_available_time_is_rejected_when_real_change_overlaps_next_day_active_appointment(): void
    {
        $doctor = $this->createDoctor('doctor-update-overnight-overlap@example.com', '01111111217');
        $availability = $this->createAvailability($doctor, '2026-04-20', '18:00:00', '20:00:00');
        $representative = $this->createRepresentative('rep-overnight-overlap@example.com', '01011111114');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $representative->id,
            'company_id' => $representative->company_id,
            'date' => '2026-04-21',
            'start_time' => '01:30:00',
            'end_time' => '01:35:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot update availability that overlaps active appointments');
    }

    public function test_doctor_update_overnight_available_time_allows_no_op_with_next_day_active_appointment_overlap(): void
    {
        $doctor = $this->createDoctor('doctor-update-overnight-noop@example.com', '01111111218');
        $availability = $this->createAvailability($doctor, '2026-04-20', '22:00:00', '02:00:00', 'available', true);
        $representative = $this->createRepresentative('rep-overnight-noop@example.com', '01011111115');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $representative->id,
            'company_id' => $representative->company_id,
            'date' => '2026-04-21',
            'start_time' => '01:30:00',
            'end_time' => '01:35:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '10:00 PM',
            'end_time' => '02:00 AM',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.ends_next_day', true);
    }

    public function test_doctor_update_available_time_can_move_weekday_without_self_overlap_conflict(): void
    {
        $doctor = $this->createDoctor('doctor-update-weekday-self-overlap@example.com', '01111111242');
        $availability = $this->createAvailability($doctor, 'monday', '22:00:00', '02:00:00', 'available', true);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => 'tuesday',
            'start_time' => '01:00 AM',
            'end_time' => '03:00 AM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.date', 'Tuesday');
        $response->assertJsonPath('data.start_time', '01:00 AM');
        $response->assertJsonPath('data.end_time', '03:00 AM');
        $response->assertJsonPath('data.ends_next_day', false);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'date' => 'tuesday',
            'start_time' => '01:00:00',
            'end_time' => '03:00:00',
            'ends_next_day' => 0,
            'status' => 'available',
        ]);
    }

    public function test_doctor_can_delete_own_available_time_when_no_active_appointment_conflict(): void
    {
        $doctor = $this->createDoctor('doctor-delete-1@example.com', '01111111194');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->deleteJson('/api/doctor/available-time/' . $availability->id);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Availability deleted successfully');

        $this->assertDatabaseMissing('doctor_availabilities', [
            'id' => $availability->id,
        ]);
    }

    public function test_doctor_delete_available_time_is_rejected_when_overlapping_active_appointment_exists(): void
    {
        $doctor = $this->createDoctor('doctor-delete-2@example.com', '01111111195');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        $representative = $this->createRepresentative('rep-a@example.com', '01011111111');

        Appointment::create([
            'doctors_id' => $doctor->id,
            'representative_id' => $representative->id,
            'company_id' => $representative->company_id,
            'date' => '2026-04-20',
            'start_time' => '09:30:00',
            'end_time' => '09:35:00',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->deleteJson('/api/doctor/available-time/' . $availability->id);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot delete availability that overlaps active appointments');
        $this->assertDatabaseHas('doctor_availabilities', ['id' => $availability->id]);
    }

    public function test_doctor_cannot_update_or_delete_another_doctors_available_time(): void
    {
        $doctor = $this->createDoctor('doctor-owner@example.com', '01111111196');
        $otherDoctor = $this->createDoctor('doctor-other@example.com', '01111111197');
        $otherAvailability = $this->createAvailability($otherDoctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $updateResponse = $this->putJson('/api/doctor/available-time/' . $otherAvailability->id, [
            'date' => '2026-04-20',
            'start_time' => '11:00 AM',
            'end_time' => '12:00 PM',
        ]);
        $updateResponse->assertStatus(404);

        $deleteResponse = $this->deleteJson('/api/doctor/available-time/' . $otherAvailability->id);
        $deleteResponse->assertStatus(404);
    }

    public function test_update_available_time_validates_time_format_and_start_before_end(): void
    {
        $doctor = $this->createDoctor('doctor-validate-1@example.com', '01111111198');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $invalidFormatResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '11:70 PM',
            'end_time' => '12:00 AM',
        ]);
        $invalidFormatResponse->assertStatus(422);
        $invalidFormatResponse->assertJsonPath('message', 'Invalid time format, please use hh:mm AM/PM or HH:mm');

        $invalidAmPmResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '00:30 AM',
            'end_time' => '01:30 AM',
        ]);
        $invalidAmPmResponse->assertStatus(422);
        $invalidAmPmResponse->assertJsonPath('message', 'Invalid time format, please use hh:mm AM/PM or HH:mm');

        $invalidRangeResponse = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '15:00',
            'end_time' => '14:00',
        ]);
        $invalidRangeResponse->assertStatus(422);
        $invalidRangeResponse->assertJsonPath('message', 'Start time must be before end time');
    }

    public function test_update_available_time_with_same_day_range_and_overnight_flag_normalizes_to_same_day(): void
    {
        $doctor = $this->createDoctor('doctor-validate-overnight-flag@example.com', '01111111224');
        $availability = $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/available-time/' . $availability->id, [
            'date' => '2026-04-20',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.ends_next_day', false);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $availability->id,
            'ends_next_day' => 0,
        ]);
    }

    public function test_save_available_time_with_weekday_text_creates_new_non_overlapping_slot(): void
    {
        $doctor = $this->createDoctor('doctor-create-1@example.com', '01111111199');
        $existingSaturdaySlot = $this->createAvailability($doctor, '2026-04-25', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'saturday',
            'start_time' => '09:30 AM',
            'end_time' => '10:30 AM',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'date' => 'Saturday',
            'start_time' => '09:30 AM',
            'end_time' => '10:30 AM',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $existingSaturdaySlot->id,
            'date' => '2026-04-25',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'saturday',
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
            'status' => 'available',
        ]);
        $this->assertSame(
            2,
            DoctorAvailability::where('doctors_id', $doctor->id)
                ->where('status', 'available')
                ->count()
        );
    }

    public function test_save_available_time_rejects_exact_duplicate_slot_on_same_weekday(): void
    {
        $doctor = $this->createDoctor('doctor-create-duplicate-weekday@example.com', '01111111241');
        $this->createAvailability($doctor, 'thursday', '09:00:00', '10:00:00');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => 'thursday',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This time conflicts with an existing availability');
        $this->assertSame(
            1,
            DoctorAvailability::query()
                ->where('doctors_id', $doctor->id)
                ->where('status', 'available')
                ->where('date', 'thursday')
                ->count()
        );
    }

    public function test_save_available_time_ignores_overlap_with_non_available_slots(): void
    {
        $doctor = $this->createDoctor('doctor-create-status-scope@example.com', '01111111214');
        $this->createAvailability($doctor, '2026-04-20', '09:00:00', '10:00:00', 'booked');
        $this->createAvailability($doctor, '2026-04-20', '09:15:00', '10:15:00', 'busy');

        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '09:30 AM',
            'end_time' => '10:30 AM',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'monday',
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_accepts_overnight_when_ends_next_day_true(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight@example.com', '01111111219');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'monday',
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
            'ends_next_day' => 1,
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_rejects_overnight_without_flag(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight-no-flag@example.com', '01111111220');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Start time must be before end time');
    }

    public function test_save_available_time_with_same_day_range_and_overnight_flag_normalizes_to_same_day(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight-invalid-flag@example.com', '01111111221');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'date' => 'Monday',
            'start_time' => '10:00 AM',
            'end_time' => '12:00 PM',
            'ends_next_day' => false,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctors_id' => $doctor->id,
            'date' => 'monday',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'ends_next_day' => 0,
            'status' => 'available',
        ]);
    }

    public function test_save_available_time_rejects_overlap_with_existing_overnight_slot_on_next_day_early_hours(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight-overlap-forward@example.com', '01111111222');
        $this->createAvailability($doctor, '2026-04-20', '22:00:00', '02:00:00', 'available', true);
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-21',
            'start_time' => '01:00',
            'end_time' => '03:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This time conflicts with an existing availability');
    }

    public function test_save_available_time_rejects_overnight_when_next_day_slot_already_exists(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight-overlap-reverse@example.com', '01111111223');
        $this->createAvailability($doctor, '2026-04-21', '01:00:00', '03:00:00');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '22:00',
            'end_time' => '02:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This time conflicts with an existing availability');
    }

    public function test_save_available_time_validates_time_and_date_format(): void
    {
        $doctor = $this->createDoctor('doctor-create-validate-format@example.com', '01111111215');
        Sanctum::actingAs($doctor, ['doctor']);

        $invalidTimeResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '25:00',
            'end_time' => '26:00',
        ]);

        $invalidTimeResponse->assertStatus(422);
        $invalidTimeResponse->assertJsonPath('message', 'Invalid time format, please use hh:mm AM/PM or HH:mm');

        $invalidStart24Response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '24:00',
            'end_time' => '23:00',
        ]);

        $invalidStart24Response->assertStatus(422);
        $invalidStart24Response->assertJsonPath('message', 'Invalid time format, please use hh:mm AM/PM or HH:mm');

        $invalidEnd2430Response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '00:00',
            'end_time' => '24:30',
        ]);

        $invalidEnd2430Response->assertStatus(422);
        $invalidEnd2430Response->assertJsonPath('message', 'Invalid time format, please use hh:mm AM/PM or HH:mm');

        $invalidDateResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => '20-04-2026',
            'start_time' => '09:00 AM',
            'end_time' => '10:00 AM',
        ]);

        $invalidDateResponse->assertStatus(422);
    }

    public function test_save_available_time_validates_start_before_end(): void
    {
        $doctor = $this->createDoctor('doctor-create-2@example.com', '01111111200');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '03:00 PM',
            'end_time' => '02:00 PM',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Start time must be before end time');

        $equalTimesResponse = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '10:00 AM',
            'end_time' => '10:00 AM',
            'ends_next_day' => true,
        ]);

        $equalTimesResponse->assertStatus(422);
        $equalTimesResponse->assertJsonPath('message', 'Start time must be before end time');
    }

    public function test_super_admin_list_doctors_resource_keeps_lowercase_weekday_date_and_orders_times(): void
    {
        $doctor = $this->createDoctor('super-admin-lowercase-date@example.com', '01111111239');
        $this->createAvailability($doctor, 'monday', '13:00:00', '14:00:00', 'available');
        $this->createAvailability($doctor, 'sunday', '23:00:00', '23:30:00', 'available');
        $this->createAvailability($doctor, 'monday', '09:00:00', '10:00:00', 'available');

        $resource = new ListDoctorsResource($doctor->load(['specialty', 'availableTimes']));
        $payload = $resource->resolve();

        $this->assertSame('sunday', $payload['available_times'][0]['date']);
        $this->assertSame('11:00 PM', $payload['available_times'][0]['start_time']);
        $this->assertSame('monday', $payload['available_times'][1]['date']);
        $this->assertSame('09:00 AM', $payload['available_times'][1]['start_time']);
        $this->assertSame('monday', $payload['available_times'][2]['date']);
        $this->assertSame('01:00 PM', $payload['available_times'][2]['start_time']);
    }

    private function assertBusyDoctorPayload(array $doctorPayload, string $fromDate, string $toDate, int $expectedAvailableCount): void
    {
        $this->assertSame('busy', $doctorPayload['status']);
        $this->assertSame($fromDate, $doctorPayload['from_date']);
        $this->assertSame($toDate, $doctorPayload['to_date']);
        $this->assertIsArray($doctorPayload['busy_period']);
        $this->assertSame($fromDate, $doctorPayload['busy_period']['from_date']);
        $this->assertSame($toDate, $doctorPayload['busy_period']['to_date']);
        $this->assertTrue((bool) $doctorPayload['busy_period']['is_active_now']);
        $this->assertCount($expectedAvailableCount, $doctorPayload['available_times']);
        if ($expectedAvailableCount > 0) {
            $this->assertSame('available', $doctorPayload['available_times'][0]['status']);
        }
    }

    private function createDoctor(string $email, string $phone): Doctors
    {
        $specialty = Specialty::firstOrCreate(['name' => 'Cardiology']);

        return Doctors::create([
            'name' => 'Doctor ' . str_replace(['@example.com', '-', '_'], '', $email),
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'specialty_id' => $specialty->id,
            'status' => 'active',
        ]);
    }

    private function createAvailability(
        Doctors $doctor,
        string $date,
        string $start,
        string $end,
        string $status = 'available',
        bool $endsNextDay = false,
        int $maxRepsPerRange = 2
    ): DoctorAvailability
    {
        return DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'ends_next_day' => $endsNextDay,
            'max_reps_per_range' => $maxRepsPerRange,
            'status' => $status,
        ]);
    }

    private function createRepresentative(string $email, string $phone): Representative
    {
        $package = Package::firstOrCreate(
            ['name' => 'Quarterly'],
            [
                'price' => 1000,
                'duration' => 90,
                'plan_type' => 'quarterly',
                'billing_months' => 3,
            ]
        );

        $company = Company::create([
            'name' => 'Company ' . $phone,
            'package_id' => $package->id,
            'phone' => '012' . substr($phone, -8),
            'email' => 'company-' . str_replace(['@', '.'], '-', $email),
            'password' => 'secret123',
            'status' => 'active',
        ]);

        return Representative::create([
            'name' => 'Rep ' . $phone,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123',
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }

    private function createTestingSchema(): void
    {
        foreach ([
            'personal_access_tokens',
            'doctor_availabilities',
            'doctor_blocks',
            'doctor_representative_favorite',
            'appointments',
            'representatives',
            'companies',
            'packages',
            'doctors',
            'specialties',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
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

        Schema::create('doctor_representative_favorite', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->boolean('is_fav')->default(false);
            $table->timestamps();
        });

        Schema::create('doctor_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('blockable_id');
            $table->string('blockable_type');
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->unsignedBigInteger('representative_id');
            $table->unsignedBigInteger('company_id');
            $table->time('start_time');
            $table->time('end_time');
            $table->date('date')->nullable();
            $table->enum('status', ['cancelled', 'confirmed', 'pending', 'left', 'suspended'])->default('pending');
            $table->string('cancelled_by')->nullable();
            $table->uuid('appointment_code')->unique();
            $table->timestamps();
        });

        Schema::create('doctor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctors_id');
            $table->string('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->unsignedInteger('max_reps_per_range')->default(2);
            $table->enum('status', ['available', 'canceled', 'booked', 'busy'])->default('available');
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
