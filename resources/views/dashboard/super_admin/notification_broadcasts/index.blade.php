@extends('dashboard.layout.main')
@section('title', 'Push Notifications')
@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
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
            <div class="col-sm-12">
                <div class="card table-card">
                    <div class="card-body">
                        <div class="text-end p-4 pb-0">
                            <a href="{{ route('notification-broadcasts.create') }}" class="btn btn-primary d-inline-flex align-item-center">
                                <i class="ti ti-plus f-18"></i> New broadcast
                            </a>
                        </div>

                        <div class="table-responsive">
                            <div class="datatable-wrapper datatable-loading no-footer searchable fixed-columns">
                                <div class="datatable-container">
                                    <table class="table table-hover datatable-table" id="pc-dt-simple" data-client-datatable="true">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Title</th>
                                                <th>Target</th>
                                                <th>Delivery</th>
                                                <th>Display</th>
                                                <th>Recipients</th>
                                                <th>Status</th>
                                                <th>Sent at</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($broadcasts as $broadcast)
                                                <tr>
                                                    <td>{{ $loop->iteration + ($broadcasts->currentPage() - 1) * $broadcasts->perPage() }}</td>
                                                    <td>
                                                        <h5 class="mb-0">{{ $broadcast->title }}</h5>
                                                        <small class="text-muted">{{ \Illuminate\Support\Str::limit($broadcast->body, 80) }}</small>
                                                    </td>
                                                    <td>
                                                        @if ($broadcast->target_type === 'all')
                                                            <span class="badge bg-info">All doctors</span>
                                                        @else
                                                            @php
                                                                $names = collect($broadcast->target_specialty_ids ?? [])
                                                                    ->map(fn($id) => $specialtiesById[$id] ?? null)
                                                                    ->filter();
                                                            @endphp
                                                            @if ($names->isEmpty())
                                                                <span class="text-muted">---</span>
                                                            @else
                                                                @foreach ($names as $name)
                                                                    <span class="badge bg-light-primary text-primary me-1">{{ $name }}</span>
                                                                @endforeach
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            $deliveryLabels = ['both' => 'Push + In-app', 'push_only' => 'Push only', 'in_app_only' => 'In-app only'];
                                                        @endphp
                                                        <span class="badge bg-primary">{{ $deliveryLabels[$broadcast->delivery_type ?? 'both'] ?? 'Push + In-app' }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-{{ ($broadcast->display_type ?? 'list') === 'modal' ? 'warning' : 'secondary' }}">
                                                            {{ ucfirst($broadcast->display_type ?? 'list') }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $broadcast->recipient_count }}</td>
                                                    <td>
                                                        @php
                                                            $statusClass = [
                                                                'pending' => 'bg-secondary',
                                                                'sending' => 'bg-warning',
                                                                'sent' => 'bg-success',
                                                                'failed' => 'bg-danger',
                                                            ][$broadcast->status] ?? 'bg-secondary';
                                                        @endphp
                                                        <span class="badge {{ $statusClass }}">{{ ucfirst($broadcast->status) }}</span>
                                                    </td>
                                                    <td>{{ $broadcast->sent_at?->format('Y-m-d H:i') ?? '---' }}</td>
                                                    <td>
                                                        <ul class="list-inline me-auto mb-0">
                                                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="View">
                                                                <a href="{{ route('notification-broadcasts.show', $broadcast->id) }}" class="avtar avtar-xs btn-link-primary">
                                                                    <i class="ti ti-eye f-18"></i>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="9" class="text-center">No broadcasts sent yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            {{ $broadcasts->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

<style>
    .table tr th {
        width: auto;
        font-size: 14px;
    }
</style>
