@extends('dashboard.layout.main')
@section('title', 'Broadcast details')
@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('notification-broadcasts.index') }}">Push Notifications</a></li>
                            <li class="breadcrumb-item" aria-current="page">#{{ $broadcast->id }}</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">{{ $broadcast->title }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        @if ($broadcast->image_url)
                            <div class="mb-3">
                                <img src="{{ $broadcast->image_url }}" alt="Broadcast image" class="img-fluid rounded" style="max-height: 280px;">
                            </div>
                        @endif

                        <h5>Body</h5>
                        <p style="white-space: pre-wrap;">{{ $broadcast->body }}</p>

                        <hr>

                        <h5>Target</h5>
                        @if ($broadcast->target_type === 'all')
                            <p><span class="badge bg-info">All doctors</span></p>
                        @else
                            @if ($specialties->isEmpty())
                                <p class="text-muted">No specialties recorded.</p>
                            @else
                                <p>
                                    @foreach ($specialties as $specialty)
                                        <span class="badge bg-light-primary text-primary me-1">{{ $specialty->name }}</span>
                                    @endforeach
                                </p>
                            @endif
                        @endif

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <strong>Status:</strong>
                                @php
                                    $statusClass = [
                                        'pending' => 'bg-secondary',
                                        'sending' => 'bg-warning',
                                        'sent' => 'bg-success',
                                        'failed' => 'bg-danger',
                                    ][$broadcast->status] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ ucfirst($broadcast->status) }}</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Recipients:</strong> {{ $broadcast->recipient_count }}
                            </div>
                            <div class="col-md-4">
                                <strong>Sent at:</strong> {{ $broadcast->sent_at?->format('Y-m-d H:i') ?? '---' }}
                            </div>
                        </div>

                        @if ($broadcast->error)
                            <hr>
                            <div class="alert alert-danger">
                                <strong>Error:</strong> {{ $broadcast->error }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
