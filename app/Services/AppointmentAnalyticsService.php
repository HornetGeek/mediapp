<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Doctors;
use App\Models\RepCompanyCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AppointmentAnalyticsService
{
    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'suspended' => 'Awaiting confirmation',
        'left' => 'Left / unconfirmed',
        'deleted' => 'Deleted',
    ];

    public function summary(Builder $query): array
    {
        $statusCounts = array_fill_keys(array_keys(self::STATUSES), 0);
        $databaseCounts = (clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($databaseCounts as $status => $count) {
            if ($status !== null && array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int) $count;
            }
        }

        $total = (clone $query)->count();
        $today = Carbon::today(config('app.timezone'))->toDateString();
        $decided = $statusCounts['confirmed'] + $statusCounts['cancelled'] + $statusCounts['left'] + $statusCounts['deleted'];

        return [
            'total' => $total,
            'today' => (clone $query)->whereDate('date', $today)->count(),
            'upcoming' => (clone $query)
                ->where('status', 'pending')
                ->whereDate('date', '>=', $today)
                ->count(),
            'unique_doctors' => (clone $query)->whereNotNull('doctors_id')->distinct()->count('doctors_id'),
            'unique_representatives' => (clone $query)->whereNotNull('representative_id')->distinct()->count('representative_id'),
            'status_counts' => $statusCounts,
            'confirmation_rate' => $decided > 0
                ? round(($statusCounts['confirmed'] / $decided) * 100, 1)
                : 0.0,
            'cancellation_rate' => $total > 0
                ? round((($statusCounts['cancelled'] + $statusCounts['deleted']) / $total) * 100, 1)
                : 0.0,
        ];
    }

    public function dailyTrend(Builder $query, ?string $fromDate = null, ?string $toDate = null): array
    {
        $end = $toDate
            ? Carbon::parse($toDate, config('app.timezone'))->startOfDay()
            : Carbon::today(config('app.timezone'));
        $start = $fromDate
            ? Carbon::parse($fromDate, config('app.timezone'))->startOfDay()
            : $end->copy()->subDays(29);

        if ($start->diffInDays($end, false) < 0) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 29) {
            $start = $end->copy()->subDays(29);
        }

        $counts = (clone $query)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select('date')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date')
            ->map(fn ($count) => (int) $count);

        $days = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'label' => $day->format('M j'),
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return [
            'days' => $days,
            'max' => max(1, ...array_column($days, 'count')),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ];
    }

    public function topDoctors(Builder $query, int $limit = 5): array
    {
        $rows = (clone $query)
            ->whereNotNull('doctors_id')
            ->select('doctors_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('doctors_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();
        $names = Doctors::whereIn('id', $rows->pluck('doctors_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'name' => $names[$row->doctors_id] ?? 'Unknown doctor',
            'count' => (int) $row->aggregate,
        ])->all();
    }

    public function topCompanies(Builder $query, int $limit = 5): array
    {
        $companyRows = (clone $query)
            ->whereNotNull('company_id')
            ->select('company_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('company_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();
        $catalogRows = (clone $query)
            ->whereNull('company_id')
            ->whereNotNull('company_catalog_id')
            ->select('company_catalog_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('company_catalog_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        $companyNames = Company::whereIn('id', $companyRows->pluck('company_id'))->pluck('name', 'id');
        $catalogNames = RepCompanyCatalog::whereIn('id', $catalogRows->pluck('company_catalog_id'))->pluck('name', 'id');

        return $companyRows
            ->map(fn ($row) => [
                'name' => $companyNames[$row->company_id] ?? 'Unknown company',
                'count' => (int) $row->aggregate,
            ])
            ->concat($catalogRows->map(fn ($row) => [
                'name' => $catalogNames[$row->company_catalog_id] ?? 'Unknown company',
                'count' => (int) $row->aggregate,
            ]))
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }
}
