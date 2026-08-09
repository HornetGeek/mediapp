<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Exports\VisitsReportExportForDashboard;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AppointmentsController extends Controller
{
    private const SORT_OPTIONS = [
        'created_desc' => 'Newest created',
        'created_asc' => 'Oldest created',
        'appointment_desc' => 'Latest scheduled date',
        'appointment_asc' => 'Earliest scheduled date',
    ];

    public function index(Request $request, AppointmentAnalyticsService $analytics)
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($filters);

        $summary = $analytics->summary(clone $query);
        $trend = $analytics->dailyTrend(clone $query, $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        $topDoctors = $analytics->topDoctors(clone $query);
        $topCompanies = $analytics->topCompanies(clone $query);
        $appointments = $this->applySort((clone $query)
            ->with([
                'doctor.specialty',
                'representative.company',
                'representative.companyCatalog',
                'company',
                'companyCatalog',
            ]), $filters['sort'])
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.super_admin.appointments.index', [
            'appointments' => $appointments,
            'summary' => $summary,
            'trend' => $trend,
            'topDoctors' => $topDoctors,
            'topCompanies' => $topCompanies,
            'filters' => $filters,
            'statuses' => AppointmentAnalyticsService::STATUSES,
            'sortOptions' => self::SORT_OPTIONS,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $appointments = $this->applySort($this->filteredQuery($filters)
            ->with([
                'doctor.specialty',
                'representative.company',
                'representative.companyCatalog',
                'company',
                'companyCatalog',
            ]), $filters['sort'])
            ->get();

        return Excel::download(
            new VisitsReportExportForDashboard($appointments),
            'appointments_'.now()->format('Y_m_d_His').'.csv',
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(AppointmentAnalyticsService::STATUSES))],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORT_OPTIONS))],
        ]);

        $filters['sort'] ??= 'created_desc';

        return $filters;
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Appointment::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $query) use ($search) {
                $query->where('appointment_code', 'like', "%{$search}%")
                    ->when(ctype_digit($search), fn (Builder $query) => $query->orWhere('id', (int) $search))
                    ->orWhereHas('doctor', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('representative', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('companyCatalog', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'created_asc' => $query->orderBy('created_at')->orderBy('id'),
            'appointment_desc' => $query->orderByDesc('date')->orderByDesc('start_time')->orderByDesc('id'),
            'appointment_asc' => $query->orderBy('date')->orderBy('start_time')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
