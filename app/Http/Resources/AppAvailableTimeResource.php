<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppAvailableTimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $normalizedDate = $this->normalizeWeekdayDate((string) $this->date);
        $maxRepsPerRange = $this->max_reps_per_range === null
            ? null
            : max(1, (int) $this->max_reps_per_range);
        $bookedRepsCount = (int) ($this->booked_reps_count ?? 0);
        $remainingRepsCount = $maxRepsPerRange === null
            ? null
            : max(0, (int) ($this->remaining_reps_count ?? ($maxRepsPerRange - $bookedRepsCount)));

        $payload = [
            'id' => $this->id,
            'date' => $normalizedDate !== null
                ? ucfirst($normalizedDate)
                : ucfirst(strtolower(trim((string) $this->date))),
            'start_time' => Carbon::parse($this->start_time)->format('h:i A'),
            'end_time' => Carbon::parse($this->end_time)->format('h:i A'),
            'ends_next_day' => (bool) $this->ends_next_day,
            'max_reps_per_range' => $maxRepsPerRange,
            'booked_reps_count' => $bookedRepsCount,
            'remaining_reps_count' => $remainingRepsCount,
            'visit_time_type' => $this->visit_time_type ?: 'between',
            'status' => $this->status,
            'is_booked_for_date' => (bool) ($this->is_booked_for_date ?? false),
        ];

        $countedForDate = $this->counted_for_date ?? null;
        if ($countedForDate !== null && $countedForDate !== '') {
            $payload['counted_for_date'] = (string) $countedForDate;
        }

        return $payload;
    }

    private function normalizeWeekdayDate(string $date): ?string
    {
        $trimmedDate = trim($date);
        if ($trimmedDate === '') {
            return null;
        }

        $weekday = strtolower($trimmedDate);
        $weekdayMap = [
            'sunday' => true,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => true,
        ];

        if (isset($weekdayMap[$weekday])) {
            return $weekday;
        }

        try {
            $dateObject = Carbon::createFromFormat('Y-m-d', $trimmedDate);
        } catch (\Exception $exception) {
            return null;
        }

        if ($dateObject->format('Y-m-d') !== $trimmedDate) {
            return null;
        }

        return strtolower($dateObject->format('l'));
    }
}
