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
	<div class="card">
            <form action="{{ route('superadmin.app.versions') }}" method="POST">
                <div class="row">

                    @csrf
                    <!-- App 1 -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Super Admin App</h5>

                                <div class="mb-3">
                                    <label class="form-label">App Version</label>
                                    <input type="text" name="apps[super_admin][version]" class="form-control"
                                        value="{{ $data['versions']['super_admin'] ?? '' }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Force Update?</label>
                                    <select name="apps[super_admin][is_forced]" class="form-control">
                                        <option value="0" {{ ($data['forced']['super_admin'] ?? 0) == 0 ? 'selected' : '' }}>
                                            False (Optional)
                                        </option>
                                        <option value="1" {{ ($data['forced']['super_admin'] ?? 0) == 1 ? 'selected' : '' }}>
                                            True (Forced)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- App 2 -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">(Company,Reps) App</h5>

                                <div class="mb-3">
                                    <label class="form-label">App Version</label>
                                    <input type="text" name="apps[company][version]" class="form-control"
                                        value="{{ $data['versions']['company'] ?? '' }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Force Update?</label>
                                    <select name="apps[company][is_forced]" class="form-control">
                                        <option value="0" {{ ($data['forced']['company'] ?? 0) == 0 ? 'selected' : '' }}>
                                            False (Optional)
                                        </option>
                                        <option value="1" {{ ($data['forced']['company'] ?? 0) == 1 ? 'selected' : '' }}>
                                            True (Forced)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- App 3 -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Doctor App</h5>

                                <div class="mb-3">
                                    <label class="form-label">App Version</label>
                                    <input type="text" name="apps[doctor][version]" class="form-control"
                                        value="{{ $data['versions']['doctor'] ?? '' }}">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Force Update?</label>
                                    <select name="apps[doctor][is_forced]" class="form-control">
                                        <option value="0" {{ ($data['forced']['doctor'] ?? 0) == 0 ? 'selected' : '' }}>
                                            False (Optional)
                                        </option>
                                        <option value="1" {{ ($data['forced']['doctor'] ?? 0) == 1 ? 'selected' : '' }}>
                                            True (Forced)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <button type="submit" class="btn btn-primary m-4">Save</button>
            </form>
        </div>
</div>
@endsection