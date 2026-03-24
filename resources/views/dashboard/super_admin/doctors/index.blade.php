@extends('dashboard.layout.main')
@section('title', 'Doctors')
@section('content')

    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{route('superadmin.dashboard')}}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Doctors</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Doctors</h2>
                        </div>
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
                                <i class="ti ti-plus f-18"></i> Add Doctor
                            </a>
                        </div>
                        {{-- modal create --}}
                        @include('dashboard.super_admin.doctors.addModel')
                        {{-- end modal create --}}
                        <div class="table-responsive">
                            <div class="datatable-wrapper datatable-loading no-footer searchable fixed-columns">
                                <div class="datatable-container">
                                    <table class="table table-hover datatable-table" id="pc-dt-simple">
                                        <thead>
                                            <tr>
                                                {{-- <th></th> --}}
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Specialty</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @isset($doctors)
                                            @php
                                                    $i = 1;
                                                @endphp
                                                @forelse ($doctors as $doctor)
                                                
                                                    <tr data-index="4">
                                                        {{-- <td>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox">
                                                            </div>
                                                        </td> --}}
                                                        <td>{{$i++}}</td>
                                                        <td>
                                                            <div class="row">
                                                                {{-- <div class="col-auto pe-0">
                                                                    <img src="../assets/images/user/avatar-5.jpg"
                                                                        alt="user-image" class="wid-40 rounded-circle">
                                                                </div> --}}
                                                                <div class="col d-flex align-items-center">
                                                                    <h5 class="mb-0">{{$doctor->name}}</h5>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>{{$doctor->email}}</td>
                                                        <td>{{$doctor->phone}}</td>
                                                        <td>{{$doctor->specialty->name ?? '---'}}</td>
                                                        <td>
                                                            <ul class="list-inline me-auto mb-0">
                                                                <li class="list-inline-item align-bottom"
                                                                    data-bs-toggle="tooltip" title="Edit">
                                                                    <a href="#" class="avtar avtar-xs btn-link-primary"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#editModal_{{$doctor->id}}">
                                                                        <i class="ti ti-edit-circle f-18"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="list-inline-item align-bottom"
                                                                    data-bs-toggle="tooltip" title="Delete">
                                                                    <a href="#" class="avtar avtar-xs btn-link-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#deleteModal_{{$doctor->id}}">
                                                                        <i class="ti ti-trash f-18"></i>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                    @include('dashboard.super_admin.doctors.editModal', ['doctor' => $doctor])
                                                    @include('dashboard.super_admin.doctors.deleteModal', ['doctor' => $doctor])
                                                @empty
                                                @endforelse
                                            @endisset

                                        </tbody>
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
