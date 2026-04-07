<div class="table-responsive">
    <div class="datatable-wrapper datatable-loading no-footer searchable fixed-columns">
        <div class="datatable-container">
            <table class="table table-hover datatable-table" id="pc-dt-simple" data-client-datatable="true">
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
                    @isset($data)
                        @php
                            $i = 1;
                        @endphp
                        @forelse ($data as $company)
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
                                        <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                            <a href="#" class="avtar avtar-xs btn-link-primary" data-bs-toggle="modal"
                                                data-bs-target="#editModal_{{ $company->id }}">
                                                <i class="ti ti-edit-circle f-18"></i>
                                            </a>
                                        </li>
                                        <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Delete">
                                            <a href="#" class="avtar avtar-xs btn-link-danger" data-bs-toggle="modal"
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
                            @include('dashboard.super_admin.companies.deleteModal', [
                                'company' => $company,
                            ])
                        @empty
                        @endforelse
                    @endisset

                </tbody>
            </table>
        </div>

    </div>
</div>
