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
                        <form action="{{ route('admin.push-notifications.send') }}" method="POST" enctype="multipart/form-data">
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

                            <div class="form-group mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    class="form-control @error('image') is-invalid @enderror">
                                @error('image')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Video</label>
                                <input type="file" name="video" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm"
                                    class="form-control @error('video') is-invalid @enderror">
                                <small class="text-muted">Maximum duration: 20 seconds. Upload either image or video, not both.</small>
                                @error('video')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="display_type" value="modal" id="display_type_modal"
                                        class="form-check-input" {{ old('display_type') === 'modal' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="display_type_modal">Show as in-app modal</label>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="is_skippable" value="1" id="is_skippable"
                                        class="form-check-input" {{ old('is_skippable', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_skippable">Skippable</label>
                                </div>
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
                                        <th>Display</th>
                                        <th>Media</th>
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
                                            <td>
                                                <span class="badge bg-{{ $campaign->display_type === 'modal' ? 'warning' : 'secondary' }}">
                                                    {{ ucfirst($campaign->display_type ?? 'list') }}
                                                </span>
                                                @if (($campaign->display_type ?? 'list') === 'modal')
                                                    <small class="d-block text-muted">{{ $campaign->is_skippable ? 'Skippable' : 'Not skippable' }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if (($campaign->media_type ?? 'none') === 'image')
                                                    <span class="badge bg-info">Image</span>
                                                @elseif (($campaign->media_type ?? 'none') === 'video')
                                                    <span class="badge bg-info">Video</span>
                                                @else
                                                    <span class="badge bg-secondary">None</span>
                                                @endif
                                            </td>
                                            <td>{{ $campaign->total_doctors }}</td>
                                            <td><span class="badge bg-success">{{ $campaign->sent_count }}</span></td>
                                            <td><span class="badge bg-danger">{{ $campaign->failed_count }}</span></td>
                                            <td>{{ $campaign->created_at?->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center">No campaigns sent yet.</td>
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
