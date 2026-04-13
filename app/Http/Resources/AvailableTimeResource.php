<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableTimeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $normalizedDate = $this->normalizeWeekdayDate((string) $this->date);

        return [
            'id' => $this->id,
            'date' => $normalizedDate ?? strtolower(trim((string) $this->date)),
            'start_time' => Carbon::parse($this->start_time)->format('h:i A'), // 12h format
            'end_time' => Carbon::parse($this->end_time)->format('h:i A'),
            'ends_next_day' => (bool) $this->ends_next_day,
            'max_reps_per_range' => max(1, (int) ($this->max_reps_per_range ?? 1)),
            'status' => $this->status,
        ];
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
