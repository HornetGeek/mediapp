@extends('dashboard.layout.main')
@section('title', 'Companies')
@section('content')
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Companies</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Companies</h2>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <select name="status" id="status" class="mt-3 form-select w-auto">
                            <option value="">All Companies</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card table-card">
                    <div class="card-body">
                        <div class="text-end p-4 pb-0">
                            <a href="#" class="btn btn-primary d-inline-flex align-item-center" data-bs-toggle="modal"
                                data-bs-target="#addModal">
                                <i class="ti ti-plus f-18"></i> Add Company
                            </a>
                        </div>
                        {{-- modal create --}}
                        @include('dashboard.super_admin.companies.addModel')
                        {{-- end modal create --}}
                        <div class="table-responsive">
                            <div class="datatable-wrapper datatable-loading no-footer searchable fixed-columns">
                                <div class="datatable-container">
                                    <table class="table table-hover datatable-table oldData" id="pc-dt-simple">
                                        <thead>
                                            <tr>
                                                {{-- <th></th> --}}
                                                <th>#</th>
                                                <th>Company Name</th>
                                                <th>phone</th>
                                                <th>Subscription Start</th>
                                                <th>Subscription End</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @isset($companies)
                                                @php
                                                    $i = 1;
                                                @endphp
                                                @forelse ($companies as $company)
                                                    <tr data-index="4">
                                                        {{-- <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td> --}}
                                                        <td>{{ $i++ }}</td>
                                                        <td>
                                                            <div class="row">
                                                                {{-- <div class="col-auto pe-0">
                                                                    <img src="../assets/images/user/avatar-5.jpg"
                                                                        alt="user-image" class="wid-40 rounded-circle">
                                                                </div> --}}
                                                                <div class="col d-flex align-items-center">
                                                                    <h5 class="mb-0">{{ $company->name }}</h5>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>{{ $company->phone }}</td>
                                                        <td>{{ $company->subscription_start }}</td>
                                                        <td>{{ $company->subscription_end }}</td>
                                                        <td>
                                                            @if ($company->status == 'active')
                                                                <span class="d-flex align-items-center gap-2"><i
                                                                        class="fas fa-circle text-success f-10 m-r-5"></i>Active</span>
                                                            @else
                                                                <span class="d-flex align-items-center gap-2"><i
                                                                        class="fas fa-circle text-danger f-10 m-r-5"></i>Inactive</span>
                                                            @endif
                                                        <td>
                                                            <ul class="list-inline me-auto mb-0">
                                                                <li class="list-inline-item align-bottom"
                                                                    data-bs-toggle="tooltip" title="Edit">
                                                                    <a href="#" class="avtar avtar-xs btn-link-primary"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#editModal_{{ $company->id }}">
                                                                        <i class="ti ti-edit-circle f-18"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="list-inline-item align-bottom"
                                                                    data-bs-toggle="tooltip" title="Delete">
                                                                    <a href="#" class="avtar avtar-xs btn-link-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#deleteModal_{{ $company->id }}">
                                                                        <i class="ti ti-trash f-18"></i>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                    @include('dashboard.super_admin.companies.editModal', [
                                                        'company' => $company,
                                                    ])
                                                    @include(
                                                        'dashboard.super_admin.companies.deleteModal',
                                                        [
                                                            'company' => $company,
                                                        ]
                                                    )
                                                @empty
                                                @endforelse
                                            @endisset

                                        </tbody>
                                    </table>
                                    <table class="table table-hover datatable-table searchedData" id="pc-dt-simple">
                                    </table>
                                </div>
                                {{-- <div class="datatable-bottom">
                                    <div class="datatable-info">Showing 1 to 5 of 18 entries</div>
                                    <nav class="datatable-pagination">
                                        <ul class="datatable-pagination-list">
                                            <li class="datatable-pagination-list-item datatable-hidden datatable-disabled">
                                                <a data-page="1" class="datatable-pagination-list-item-link">‹</a></li>
                                            <li class="datatable-pagination-list-item datatable-active"><a data-page="1"
                                                    class="datatable-pagination-list-item-link">1</a></li>
                                            <li class="datatable-pagination-list-item"><a data-page="2"
                                                    class="datatable-pagination-list-item-link">2</a></li>
                                            <li class="datatable-pagination-list-item"><a data-page="3"
                                                    class="datatable-pagination-list-item-link">3</a></li>
                                            <li class="datatable-pagination-list-item"><a data-page="4"
                                                    class="datatable-pagination-list-item-link">4</a></li>
                                            <li class="datatable-pagination-list-item"><a data-page="2"
                                                    class="datatable-pagination-list-item-link">›</a></li>
                                        </ul>
                                    </nav>
                                </div> --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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

@section('js')
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $(document).ready(function() {
            $("#status").on('change', function() {
                
                let value = $(this).val();
                let _token = $('meta[name="csrf-token"]').attr('content');
                let url = "{{ route('companies.status') }}";
                // console.log(value);
                if (value) {
                    $('.oldData').hide();
                    $('.searchedData').show();
                } else {
                    $('.preData').show();
                    $('.searchedData').hide();
                }

                $.ajax({
                    url: url,
                    type: 'GET',
                    data: {
                        query: value,
                        _token: _token,
                    },
                    success: function(data) {
                        $('.searchedData').html(data);
                        // console.log(data);
                        // $('.searchedData').html(data);
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                    }
                });
            });
        });
    </script>
@endsection
