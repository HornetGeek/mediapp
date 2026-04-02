<?php

namespace Tests\Feature\Doctors;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\DoctorAvailability;
use App\Models\Doctors;
use App\Models\Package;
use App\Models\Representative;
use App\Models\Specialty;
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
        $response->assertJsonPath('data.date', 'monday');
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

    public function test_doctor_update_available_time_rejects_move_to_weekday_with_existing_available_slot(): void
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

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'An available slot already exists for this weekday');

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $mondaySlot->id,
            'date' => '2026-04-20',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $tuesdaySlot->id,
            'date' => '2026-04-21',
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

    public function test_update_available_time_rejects_invalid_overnight_flagged_payload(): void
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

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'End time must be before start time when ends_next_day is true');
    }

    public function test_save_available_time_with_weekday_text_upserts_existing_available_slot(): void
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
            'date' => 'saturday',
            'start_time' => '09:30 AM',
            'end_time' => '10:30 AM',
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('doctor_availabilities', [
            'id' => $existingSaturdaySlot->id,
            'date' => 'saturday',
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
            'status' => 'available',
        ]);
        $this->assertSame(
            1,
            DoctorAvailability::where('doctors_id', $doctor->id)
                ->where('status', 'available')
                ->where('date', 'saturday')
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

    public function test_save_available_time_rejects_invalid_overnight_flagged_payload(): void
    {
        $doctor = $this->createDoctor('doctor-create-overnight-invalid-flag@example.com', '01111111221');
        Sanctum::actingAs($doctor, ['doctor']);

        $response = $this->putJson('/api/doctor/save-available-time', [
            'date' => '2026-04-20',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'ends_next_day' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'End time must be before start time when ends_next_day is true');
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
        bool $endsNextDay = false
    ): DoctorAvailability
    {
        return DoctorAvailability::create([
            'doctors_id' => $doctor->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'ends_next_day' => $endsNextDay,
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
