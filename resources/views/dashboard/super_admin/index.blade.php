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
                    <h6 class="mb-2 f-w-400 text-muted">Ongoing appointments</h6>
                    <h4 class="mb-3">{{ $data['confirmed_visits'] }} </h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Cancelled appointments</h6>
                    <h4 class="mb-3">{{ $data['cancelled_visits'] }} </h4>
                </div>
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
