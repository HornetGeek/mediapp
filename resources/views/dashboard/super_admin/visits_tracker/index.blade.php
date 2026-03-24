@extends('dashboard.layout.main')

@section('content')
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Vistits Tracker</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Vistits Tracker</h2>
                        </div>
                        <div class="col-md-12 mt-3">
                        <a href="{{ route('visits.report.csv') }}" class="btn btn-sm btn-success">تصدير  CSV <i class="ti ti-file-analytics"></i></a>
                        <a href="{{ route('visits.report.pdf') }}" class="btn btn-sm btn-danger">تصدير  PDF <i class="ti ti-book"></i></a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row"><!-- [ sample-page ] start -->
            @isset($get_data)
                @foreach ($get_data as $item)
                    <div class="col-md-6 col-xl-4">
                        <div class="card">
                            <div class="card-header">

                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0"><img src="../assets/images/user/avatar-1.jpg" alt="user-image"
                                            class="wid-40 rounded-circle"></div>
                                    <div class="flex-grow-1 mx-3">
                                        <h6 class="mb-1">{{ $item['doctor']['name'] }}</h6>
                                        <p class="text-muted text-sm mb-0">{{ $item['doctor']['specialization'] }}</p>
                                    </div>
                                    <div class="dropdown"><a class="avtar avtar-s btn-link-secondary dropdown-toggle arrow-none"
                                            href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                            aria-expanded="false"><i class="ti ti-dots-vertical f-18"></i></a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="{{route('visits.delete', $item['id'])}}">Delete</a>
                                            <a class="dropdown-item" href="{{ route('visitsId.report.csv', $item['id']) }}">تصدير CSV</a>
                                            <a class="dropdown-item" href="{{ route('visitsId.report.pdf', $item['id']) }}">تصدير PDF</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <div class="flex-shrink-0"><img src="../assets/images/user/avatar-1.jpg" alt="user-image"
                                            class="wid-40 rounded-circle"></div>
                                    <div class="flex-grow-1 mx-3">
                                        <h6 class="mb-1">{{ $item['representative']['name'] }}</h6>
                                        <p class="text-muted text-sm mb-0">{{ $item['representative']['company_name'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">

                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <div class="d-inline-flex align-items-center justify-content-start w-100"><i
                                                class="ti ti-calendar"></i>
                                            <p class="mb-0 ms-2 text-truncate">{{ $item['date'] }}</p>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="d-inline-flex align-items-center justify-content-start w-100"><i
                                                class="ti ti-calendar-time"></i>
                                            <p class="mb-0 ms-2 text-truncate">{{ $item['start_time'] }}-{{ $item['end_time'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex align-items-center justify-content-end">
                                    {{-- <p class="mb-0 text-muted">Updated in 2 min ago</p> --}}
                                    <span
                                        class="badge bg-light-{{ $item['status'] === 'cancelled' ? 'danger' : 'success' }}">{{ $item['status'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endisset


            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
@endsection




<style>
    .table tr th {
        width: auto;
        font-size: 14px
    }
</style>
