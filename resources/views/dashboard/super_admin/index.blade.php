@extends('dashboard.layout.main')
@section('title', 'Dashboard - Home')
@section('content')
<div class="pc-content">
    <!-- [ breadcrumb ] start -->
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12 d-flex justify-content-between">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Home</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{route('superadmin.dashboard')}}">Home</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0)">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Home</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- [ breadcrumb ] end -->
    <!-- [ Main Content ] start -->
    <div class="row">
        <!-- [ sample-page ] start -->
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Doctors</h6>
                    <h4 class="mb-3">{{$data['total_doctors']}}</span></h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Companies</h6>
                    <h4 class="mb-3">{{$data['total_companies']}}</h4>
                    
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Appointments</h6>
                    <h4 class="mb-3">{{ number_format($data['appointment_summary']['total']) }}</h4>
                    <a href="{{ route('appointments.index') }}" class="small">View all appointments <i class="ti ti-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Confirmation Rate</h6>
                    <h4 class="mb-3">{{ $data['appointment_summary']['confirmation_rate'] }}%</h4>
                    <small class="text-muted">Of appointments with a final outcome</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted mb-1">Today</div><h3 class="mb-0">{{ number_format($data['appointment_summary']['today']) }}</h3></div>
                <span class="avtar bg-light-primary text-primary"><i class="ti ti-calendar"></i></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted mb-1">Upcoming Pending</div><h3 class="mb-0">{{ number_format($data['appointment_summary']['upcoming']) }}</h3></div>
                <span class="avtar bg-light-warning text-warning"><i class="ti ti-clock"></i></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted mb-1">Confirmed Visits</div><h3 class="mb-0">{{ number_format($data['appointment_summary']['status_counts']['confirmed']) }}</h3></div>
                <span class="avtar bg-light-success text-success"><i class="ti ti-circle-check"></i></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                <div><div class="text-muted mb-1">Cancelled</div><h3 class="mb-0">{{ number_format($data['appointment_summary']['status_counts']['cancelled']) }}</h3></div>
                <span class="avtar bg-light-danger text-danger"><i class="ti ti-circle-x"></i></span>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4 dashboard-appointments">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><h5 class="mb-1">Appointments over the last 30 days</h5><small class="text-muted">All appointments created by the applications</small></div>
                    <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-light-primary">Full analytics</a>
                </div>
                <div class="card-body">
                    <div class="dashboard-trend">
                        @foreach ($data['appointment_trend']['days'] as $day)
                            <div class="dashboard-trend-day" title="{{ $day['label'] }}: {{ $day['count'] }}">
                                <span>{{ $day['count'] ?: '' }}</span>
                                <i style="height: {{ max(3, ($day['count'] / $data['appointment_trend']['max']) * 135) }}px"></i>
                                <small>{{ $loop->first || $loop->last || $loop->iteration % 5 === 0 ? $day['label'] : '' }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Status overview</h5></div>
                <div class="card-body">
                    @php
                        $dashboardStatuses = [
                            'pending' => ['Pending', '#e9a319'],
                            'confirmed' => ['Confirmed', '#2ca87f'],
                            'cancelled' => ['Cancelled', '#dc2626'],
                            'suspended' => ['Awaiting confirmation', '#4680ff'],
                            'left' => ['Left / unconfirmed', '#6c757d'],
                            'deleted' => ['Deleted', '#212529'],
                        ];
                    @endphp
                    @foreach ($dashboardStatuses as $status => [$label, $color])
                        @php
                            $count = $data['appointment_summary']['status_counts'][$status];
                            $percentage = $data['appointment_summary']['total'] > 0 ? round($count / $data['appointment_summary']['total'] * 100, 1) : 0;
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1"><span>{{ $label }}</span><strong>{{ $count }} <small class="text-muted fw-normal">({{ $percentage }}%)</small></strong></div>
                            <div class="progress" style="height: 6px"><div class="progress-bar" style="width: {{ $percentage }}%; background-color: {{ $color }}"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">Latest appointments</h5><small class="text-muted">Most recent scheduled appointments across the platform</small></div>
            <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-primary">View all</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">ID</th><th>Doctor</th><th>Representative</th><th>Company</th><th>Date & time</th><th class="pe-4">Status</th></tr></thead>
                    <tbody>
                        @forelse ($latestAppointments as $appointment)
                            @php
                                $badge = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger', 'suspended' => 'info', 'left' => 'secondary', 'deleted' => 'dark'][$appointment->status] ?? 'secondary';
                            @endphp
                            <tr>
                                <td class="ps-4 fw-semibold">#{{ $appointment->id }}</td>
                                <td>{{ $appointment->doctor?->name ?? 'Deleted doctor' }}<small class="d-block text-muted">{{ $appointment->doctor?->specialty?->name ?? '' }}</small></td>
                                <td>{{ $appointment->representative?->name ?? 'Deleted representative' }}</td>
                                <td>{{ $appointment->company?->name ?? $appointment->companyCatalog?->name ?? '—' }}</td>
                                <td class="text-nowrap">{{ $appointment->date?->format('M d, Y') ?? '—' }}<small class="d-block text-muted">{{ $appointment->start_time?->format('h:i A') ?? '—' }}</small></td>
                                <td class="pe-4"><span class="badge bg-light-{{ $badge }} text-{{ $badge }} text-capitalize">{{ $appointment->status ?? 'Unknown' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No appointments have been created yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Feedback</h5>
                    <form action="{{ route('superadmin.email.feedback') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="email_feedback" class="form-label">Email for receiving feedback:</label>
                            <input type="email" class="form-control" id="email_feedback" name="email_feedback" value="{{ old('email_feedback', $data['feedback_email']) }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @php
        $appCards = [
            'company' => '(Company,Reps) App',
            'doctor' => 'Doctor App',
        ];
        $platformLabels = [
            'both' => 'Legacy / No-platform',
            'android' => 'Android',
            'ios' => 'iOS',
        ];
    @endphp
    <div class="card">
        <form action="{{ route('superadmin.app.versions') }}" method="POST">
            @csrf
            <div class="row p-3">
                @foreach ($appCards as $appType => $appTitle)
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">{{ $appTitle }}</h5>
                                @foreach ($platformLabels as $platform => $platformLabel)
                                    <div class="border rounded p-3 mb-3">
                                        <h6 class="mb-3">{{ $platformLabel }}</h6>
                                        <div class="mb-3">
                                            <label class="form-label">App Version</label>
                                            <input
                                                type="text"
                                                name="apps[{{ $appType }}][{{ $platform }}][version]"
                                                class="form-control"
                                                value="{{ old("apps.$appType.$platform.version", $data['versions'][$appType][$platform] ?? '') }}"
                                            >
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label">Force Update?</label>
                                            <select name="apps[{{ $appType }}][{{ $platform }}][is_forced]" class="form-control">
                                                <option
                                                    value="0"
                                                    {{ (int) old("apps.$appType.$platform.is_forced", $data['forced'][$appType][$platform] ?? 0) === 0 ? 'selected' : '' }}
                                                >
                                                    False (Optional)
                                                </option>
                                                <option
                                                    value="1"
                                                    {{ (int) old("apps.$appType.$platform.is_forced", $data['forced'][$appType][$platform] ?? 0) === 1 ? 'selected' : '' }}
                                                >
                                                    True (Forced)
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary m-4">Save</button>
        </form>
    </div>
</div>
@endsection

<style>
    .dashboard-appointments .dashboard-trend { min-height: 190px; display: flex; align-items: flex-end; gap: 5px; padding-top: 20px; overflow-x: auto; }
    .dashboard-appointments .dashboard-trend-day { flex: 1 0 14px; min-width: 14px; height: 170px; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; }
    .dashboard-appointments .dashboard-trend-day > span { min-height: 16px; font-size: 10px; color: #6c757d; }
    .dashboard-appointments .dashboard-trend-day > i { display: block; width: 100%; max-width: 22px; min-height: 3px; background: linear-gradient(180deg, #4680ff, #79a1ff); border-radius: 5px 5px 0 0; }
    .dashboard-appointments .dashboard-trend-day > small { height: 22px; padding-top: 5px; font-size: 9px; color: #6c757d; white-space: nowrap; }
</style>
