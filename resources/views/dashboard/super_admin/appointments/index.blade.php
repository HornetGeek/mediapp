@extends('dashboard.layout.main')

@section('title', 'Appointments & Analytics')

@section('content')
    @php
        $statusStyles = [
            'pending' => ['warning', 'ti-clock'],
            'confirmed' => ['success', 'ti-circle-check'],
            'cancelled' => ['danger', 'ti-circle-x'],
            'suspended' => ['info', 'ti-hourglass'],
            'left' => ['secondary', 'ti-walk'],
            'deleted' => ['dark', 'ti-trash'],
        ];
        $statusBarColors = [
            'pending' => '#e9a319',
            'confirmed' => '#2ca87f',
            'cancelled' => '#dc2626',
            'suspended' => '#4680ff',
            'left' => '#6c757d',
            'deleted' => '#212529',
        ];
    @endphp

    <div class="pc-content appointments-page">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center g-3">
                    <div class="col-md-7">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Appointments</li>
                        </ul>
                        <div class="page-header-title">
                            <h2 class="mb-1">Appointments & Analytics</h2>
                            <p class="text-muted mb-0">Every appointment created in the mobile applications, across all doctors and companies.</p>
                        </div>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <a href="{{ route('appointments.export', request()->query()) }}" class="btn btn-success">
                            <i class="ti ti-file-spreadsheet me-1"></i> Export filtered CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('appointments.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ti ti-search"></i></span>
                            <input type="search" class="form-control" id="search" name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                placeholder="Code, doctor, representative or company">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-5">
                        <label for="from_date" class="form-label">From date</label>
                        <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
                    </div>
                    <div class="col-lg-2 col-md-5">
                        <label for="to_date" class="form-label">To date</label>
                        <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
                    </div>
                    <div class="col-lg-2 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        <a href="{{ route('appointments.index') }}" class="btn btn-light-secondary" title="Clear filters">
                            <i class="ti ti-refresh"></i>
                        </a>
                    </div>
                </form>
                @error('to_date')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-primary text-primary"><i class="ti ti-calendar-event"></i></span>
                        <div><div class="text-muted">Total appointments</div><h3 class="mb-0">{{ number_format($summary['total']) }}</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-info text-info"><i class="ti ti-calendar-stats"></i></span>
                        <div><div class="text-muted">Appointments today</div><h3 class="mb-0">{{ number_format($summary['today']) }}</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-warning text-warning"><i class="ti ti-calendar-up"></i></span>
                        <div><div class="text-muted">Upcoming pending</div><h3 class="mb-0">{{ number_format($summary['upcoming']) }}</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-success text-success"><i class="ti ti-chart-donut"></i></span>
                        <div><div class="text-muted">Confirmation rate</div><h3 class="mb-0">{{ $summary['confirmation_rate'] }}%</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-success text-success"><i class="ti ti-circle-check"></i></span>
                        <div><div class="text-muted">Confirmed visits</div><h3 class="mb-0">{{ number_format($summary['status_counts']['confirmed']) }}</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-danger text-danger"><i class="ti ti-circle-x"></i></span>
                        <div><div class="text-muted">Cancellation rate</div><h3 class="mb-0">{{ $summary['cancellation_rate'] }}%</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-primary text-primary"><i class="ti ti-stethoscope"></i></span>
                        <div><div class="text-muted">Doctors involved</div><h3 class="mb-0">{{ number_format($summary['unique_doctors']) }}</h3></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card metric-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="metric-icon bg-light-secondary text-secondary"><i class="ti ti-users"></i></span>
                        <div><div class="text-muted">Representatives involved</div><h3 class="mb-0">{{ number_format($summary['unique_representatives']) }}</h3></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Appointment trend</h5>
                            <small class="text-muted">{{ $trend['from'] }} to {{ $trend['to'] }}</small>
                        </div>
                        <span class="badge bg-light-primary text-primary">30 days maximum</span>
                    </div>
                    <div class="card-body">
                        <div class="trend-chart" role="img" aria-label="Daily appointment counts">
                            @foreach ($trend['days'] as $day)
                                <div class="trend-day" title="{{ $day['label'] }}: {{ $day['count'] }} appointments">
                                    <span class="trend-value">{{ $day['count'] ?: '' }}</span>
                                    <span class="trend-bar" style="height: {{ max(3, ($day['count'] / $trend['max']) * 150) }}px"></span>
                                    <small>{{ $loop->first || $loop->last || $loop->iteration % 5 === 0 ? $day['label'] : '' }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Status breakdown</h5></div>
                    <div class="card-body">
                        @foreach ($statuses as $status => $label)
                            @php
                                $count = $summary['status_counts'][$status];
                                $percentage = $summary['total'] > 0 ? round(($count / $summary['total']) * 100, 1) : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><i class="ti {{ $statusStyles[$status][1] }} me-1"></i>{{ $label }}</span>
                                    <strong>{{ number_format($count) }} <small class="text-muted fw-normal">({{ $percentage }}%)</small></strong>
                                </div>
                                <div class="progress" style="height: 7px">
                                    <div class="progress-bar" style="width: {{ $percentage }}%; background: {{ $statusBarColors[$status] }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Top doctors</h5></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse ($topDoctors as $doctor)
                                <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3">
                                    <span><i class="ti ti-stethoscope text-primary me-2"></i>{{ $doctor['name'] }}</span>
                                    <span class="badge bg-light-primary text-primary">{{ number_format($doctor['count']) }}</span>
                                </div>
                            @empty
                                <div class="text-muted text-center py-4">No doctor data for this selection.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Top companies</h5></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse ($topCompanies as $company)
                                <div class="list-group-item d-flex align-items-center justify-content-between px-4 py-3">
                                    <span><i class="ti ti-building text-primary me-2"></i>{{ $company['name'] }}</span>
                                    <span class="badge bg-light-primary text-primary">{{ number_format($company['count']) }}</span>
                                </div>
                            @empty
                                <div class="text-muted text-center py-4">No company data for this selection.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">All appointments</h5>
                    <small class="text-muted">Showing {{ $appointments->firstItem() ?? 0 }}–{{ $appointments->lastItem() ?? 0 }} of {{ number_format($appointments->total()) }}</small>
                </div>
                @if (array_filter($filters))
                    <span class="badge bg-light-info text-info">Filtered results</span>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Appointment</th>
                                <th>Doctor</th>
                                <th>Representative</th>
                                <th>Company</th>
                                <th>Date & time</th>
                                <th>Status</th>
                                <th class="pe-4">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $appointment)
                                @php
                                    $companyName = $appointment->company?->name
                                        ?? $appointment->companyCatalog?->name
                                        ?? $appointment->representative?->company?->name
                                        ?? $appointment->representative?->companyCatalog?->name
                                        ?? '—';
                                    $status = $appointment->status ?? 'unknown';
                                    $style = $statusStyles[$status] ?? ['secondary', 'ti-help'];
                                @endphp
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold">#{{ $appointment->id }}</div>
                                        <small class="text-muted appointment-code" title="{{ $appointment->appointment_code }}">{{ $appointment->appointment_code }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $appointment->doctor?->name ?? 'Deleted doctor' }}</div>
                                        <small class="text-muted">{{ $appointment->doctor?->specialty?->name ?? 'No specialty' }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $appointment->representative?->name ?? 'Deleted representative' }}</div>
                                        <small class="text-muted">{{ $appointment->representative?->phone ?? '' }}</small>
                                    </td>
                                    <td>{{ $companyName }}</td>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $appointment->date?->format('M d, Y') ?? 'No date' }}</div>
                                        <small class="text-muted">
                                            {{ $appointment->start_time?->format('h:i A') ?? '—' }} – {{ $appointment->end_time?->format('h:i A') ?? '—' }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light-{{ $style[0] }} text-{{ $style[0] }} text-capitalize">
                                            <i class="ti {{ $style[1] }} me-1"></i>{{ $statuses[$status] ?? $status }}
                                        </span>
                                        @if ($appointment->cancelled_by)
                                            <small class="d-block text-muted mt-1" title="Cancelled/changed by">{{ $appointment->cancelled_by }}</small>
                                        @endif
                                    </td>
                                    <td class="pe-4 text-nowrap"><small class="text-muted">{{ $appointment->updated_at?->diffForHumans() ?? '—' }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="ti ti-calendar-off f-36 text-muted"></i>
                                        <h5 class="mt-3 mb-1">No appointments found</h5>
                                        <p class="text-muted mb-0">Try clearing or changing the filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($appointments->hasPages())
                <div class="card-footer d-flex justify-content-center">
                    {{ $appointments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

<style>
    .appointments-page .metric-card { border: 0; box-shadow: 0 2px 12px rgba(15, 23, 42, .06); }
    .appointments-page .metric-icon { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; flex: 0 0 auto; }
    .appointments-page .trend-chart { min-height: 205px; display: flex; align-items: flex-end; gap: 5px; padding-top: 24px; overflow-x: auto; }
    .appointments-page .trend-day { flex: 1 0 14px; min-width: 14px; height: 185px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; position: relative; }
    .appointments-page .trend-bar { width: 100%; max-width: 22px; min-height: 3px; border-radius: 5px 5px 0 0; background: linear-gradient(180deg, #4680ff 0%, #79a1ff 100%); transition: opacity .2s; }
    .appointments-page .trend-day:hover .trend-bar { opacity: .72; }
    .appointments-page .trend-value { font-size: 10px; color: #6c757d; min-height: 16px; }
    .appointments-page .trend-day small { height: 22px; padding-top: 5px; font-size: 9px; white-space: nowrap; color: #6c757d; }
    .appointments-page .appointment-code { display: block; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .appointments-page .table > :not(caption) > * > * { padding-top: .9rem; padding-bottom: .9rem; }
</style>
