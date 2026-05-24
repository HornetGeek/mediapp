<?php

namespace Tests\Unit\Services;

use App\Models\DoctorAvailability;
use App\Services\AvailabilityOccurrenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class AvailabilityOccurrenceServiceTest extends TestCase
{
    private AvailabilityOccurrenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityOccurrenceService();
    }

    public function test_parse_explicit_request_date_returns_null_when_missing(): void
    {
        $request = Request::create('/api/reps/doctors/all', 'GET');

        $this->assertNull($this->service->parseExplicitRequestDate($request));
    }

    public function test_parse_explicit_request_date_returns_valid_date(): void
    {
        $request = Request::create('/api/reps/doctors/all', 'GET', ['date' => '2026-05-26']);

        $this->assertSame('2026-05-26', $this->service->parseExplicitRequestDate($request));
    }

    public function test_resolve_next_occurrence_date_returns_today_for_matching_weekday_before_window_ends(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 26, 9, 30, 0, AvailabilityOccurrenceService::TIMEZONE));

        try {
            $availability = $this->makeAvailability('tuesday', '09:00:00', '11:00:00');

            $this->assertSame('2026-05-26', $this->service->resolveNextOccurrenceDate($availability));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_resolve_next_occurrence_date_skips_today_when_window_already_ended(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 26, 11, 30, 0, AvailabilityOccurrenceService::TIMEZONE));

        try {
            $availability = $this->makeAvailability('tuesday', '09:00:00', '10:00:00');

            $this->assertSame('2026-06-02', $this->service->resolveNextOccurrenceDate($availability));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_resolve_next_occurrence_date_finds_next_weekday_from_non_matching_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 26, 10, 0, 0, AvailabilityOccurrenceService::TIMEZONE));

        try {
            $availability = $this->makeAvailability('monday', '09:00:00', '10:00:00');

            $this->assertSame('2026-06-01', $this->service->resolveNextOccurrenceDate($availability));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_resolve_next_occurrence_date_returns_null_for_past_fixed_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 26, 10, 0, 0, AvailabilityOccurrenceService::TIMEZONE));

        try {
            $availability = $this->makeAvailability('2026-05-20', '09:00:00', '10:00:00');

            $this->assertNull($this->service->resolveNextOccurrenceDate($availability));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_resolve_next_occurrence_date_returns_future_fixed_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 26, 10, 0, 0, AvailabilityOccurrenceService::TIMEZONE));

        try {
            $availability = $this->makeAvailability('2026-06-10', '09:00:00', '10:00:00');

            $this->assertSame('2026-06-10', $this->service->resolveNextOccurrenceDate($availability));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function makeAvailability(string $date, string $startTime, string $endTime): DoctorAvailability
    {
        return new DoctorAvailability([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'ends_next_day' => false,
            'status' => 'available',
        ]);
    }
}
