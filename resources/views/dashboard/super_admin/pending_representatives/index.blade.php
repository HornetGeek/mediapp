@extends('dashboard.layout.main')

@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Pending Representatives</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Pending Representatives</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card table-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Requested Company</th>
                                        <th>Line</th>
                                        <th>Areas</th>
                                        <th>Registered At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($representatives as $representative)
                                        <tr>
                                            <td>{{ $representatives->firstItem() + $loop->index }}</td>
                                            <td>{{ $representative->name }}</td>
                                            <td>{{ $representative->email }}</td>
                                            <td>{{ $representative->phone }}</td>
                                            <td>{{ $representative->requested_company_name ?? '-' }}</td>
                                            <td>{{ $representative->requested_line_name ?? '-' }}</td>
                                            <td>
                                                @php
                                                    $areas = collect($representative->requested_area_names ?? [])
                                                        ->filter()
                                                        ->values();
                                                @endphp
                                                {{ $areas->isNotEmpty() ? $areas->implode(', ') : '-' }}
                                            </td>
                                            <td>{{ optional($representative->created_at)->format('Y-m-d H:i') }}</td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <form action="{{ route('pending-representatives.approve', $representative->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            Accept
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('pending-representatives.reject', $representative->id) }}" method="POST" onsubmit="return confirm('Reject this representative registration?')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-4">No pending representative registrations.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($representatives->hasPages())
                            <div class="p-3">
                                {{ $representatives->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
