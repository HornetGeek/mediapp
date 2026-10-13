@extends('dashboard.layout.main')
@section('title', 'Representatives')

@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item" aria-current="page">Representatives</li>
                </ul>
                <div class="page-header-title">
                    <h2 class="mb-0">Representatives</h2>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('superadmin.representatives.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label for="search" class="form-label">Search representatives</label>
                        <input type="search" class="form-control" id="search" name="search" value="{{ $search }}"
                            placeholder="Name, email, phone, company, line, area or status">
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="{{ route('superadmin.representatives.index') }}" class="btn btn-light-secondary">Clear</a>
                    </div>
                </form>
                @error('search')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

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
                                <th>Company</th>
                                <th>Line</th>
                                <th>Work areas</th>
                                <th>Registration</th>
                                <th>Account</th>
                                <th>Registered at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($representatives as $representative)
                                @php
                                    $companyName = $representative->company?->name
                                        ?? $representative->companyCatalog?->name
                                        ?? $representative->requested_company_name;
                                    $lineNames = collect([$representative->requested_line_name])
                                        ->merge($representative->lines->pluck('name'))
                                        ->filter()
                                        ->unique()
                                        ->implode(', ');
                                    $areaNames = collect($representative->requested_area_names ?? [])
                                        ->merge($representative->areas->pluck('name'))
                                        ->filter()
                                        ->unique()
                                        ->implode(', ');
                                @endphp
                                <tr>
                                    <td>{{ $representatives->firstItem() + $loop->index }}</td>
                                    <td>{{ $representative->name }}</td>
                                    <td>{{ $representative->email }}</td>
                                    <td>{{ $representative->phone ?: '—' }}</td>
                                    <td>{{ $companyName ?: '—' }}</td>
                                    <td>{{ $lineNames ?: '—' }}</td>
                                    <td>{{ $areaNames ?: '—' }}</td>
                                    <td>{{ ucfirst($representative->registration_status ?? 'active') }}</td>
                                    <td>{{ ucfirst($representative->status ?? 'active') }}</td>
                                    <td>{{ $representative->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        {{ $search !== '' ? 'No representatives match your search.' : 'No representatives found.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($representatives->hasPages())
                    <div class="p-3">{{ $representatives->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
