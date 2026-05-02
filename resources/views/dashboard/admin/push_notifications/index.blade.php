@extends('dashboard.layout.main')
@section('title', 'Push Notifications')
@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Push Notifications</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Push Notifications</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Send to Doctors by Specialty</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.push-notifications.send') }}" method="POST">
                            @csrf

                            <div class="form-group mb-3">
                                <label class="form-label">Specialty</label>
                                <select name="specialty_id" class="form-select @error('specialty_id') is-invalid @enderror" required>
                                    <option value="">Select specialty</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}" {{ (int) old('specialty_id') === (int) $specialty->id ? 'selected' : '' }}>
                                            {{ $specialty->name }} ({{ $specialty->doctors_count }} doctors)
                                        </option>
                                    @endforeach
                                </select>
                                @error('specialty_id')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" maxlength="255"
                                    class="form-control @error('title') is-invalid @enderror"
                                    value="{{ old('title') }}" required>
                                @error('title')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Body</label>
                                <textarea name="body" rows="5" maxlength="2000"
                                    class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                                @error('body')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-send f-18"></i> Send Notification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card table-card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Campaigns</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Specialty</th>
                                        <th>Total</th>
                                        <th>Sent</th>
                                        <th>Failed</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($campaigns as $campaign)
                                        <tr>
                                            <td>{{ $loop->iteration + ($campaigns->currentPage() - 1) * $campaigns->perPage() }}</td>
                                            <td>
                                                <h6 class="mb-0">{{ $campaign->title }}</h6>
                                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($campaign->body, 70) }}</small>
                                            </td>
                                            <td>{{ $campaign->specialty?->name ?? '---' }}</td>
                                            <td>{{ $campaign->total_doctors }}</td>
                                            <td><span class="badge bg-success">{{ $campaign->sent_count }}</span></td>
                                            <td><span class="badge bg-danger">{{ $campaign->failed_count }}</span></td>
                                            <td>{{ $campaign->created_at?->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">No campaigns sent yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3">
                            {{ $campaigns->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
