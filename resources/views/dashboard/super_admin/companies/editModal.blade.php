<form action="{{ route('companies.update', $company->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="modal fade" id="editModal_{{ $company->id }}" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="mb-0">Edit Company:{{ $company->name }}</h5>
                    <a href="#" class="avtar avtar-s btn-link-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x f-20"></i>
                    </a>
                </div>

                <div class="modal-body">

                    <div class="row">
                        <div class="col-sm-12">
                            <h4>Company Info</h4>
                            <hr>
                            <div class="form-group">
                                <label class="form-label">Company</label>
                                <select class="form-select @error('package_id') is-invalid @enderror" name="package_id"
                                    required>
                                    <option>Select Package</option>
                                    @foreach ($packages as $package)
                                        <option value="{{ $package->id }}"
                                            {{ $company->package_id == $package->id ? 'selected' : '' }}>
                                            {{ $package->name }} / {{ $package->duration . ' Days' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $company->name) }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">email</label>
                                <input type="text" class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $company->email) }}" name="email">
                                @error('email')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    value="{{ old('password') }}" name="password">
                                @error('password')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                    value="{{ old('phone', $company->phone) }}" name="phone">
                                @error('phone')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Subscription Start </label>
                                <input type="date"
                                    class="form-control @error('subscription_start') is-invalid @enderror"
                                    value="{{ old('subscription_start', $company->subscription_start) }}"
                                    name="subscription_start">
                                @error('subscription_start')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Visits Per Day</label>
                                <input type="text" class="form-control @error('visits_per_day') is-invalid @enderror"
                                    value="{{ old('visits_per_day', $company->visits_per_day) }}"
                                    name="visits_per_day">
                                @error('visits_per_day')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Number Of Reps</label>
                                <input type="text" class="form-control @error('num_of_reps') is-invalid @enderror"
                                    value="{{ old('num_of_reps', $company->num_of_reps) }}" name="num_of_reps">
                                @error('num_of_reps')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group mt-2">
                                <label class="form-label">Status</label>
                                @php
                                    $selectedStatus = old('status', $company->status);
                                @endphp

                                <select class="form-select @error('status') is-invalid @enderror" name="status"
                                    required>
                                    <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive" {{ $selectedStatus === 'inactive' ? 'selected' : '' }}>
                                        Freez</option>
                                </select>
                            </div>

                        </div>
                        {{-- <div class="col-sm-6">
                            <h4>Owner Info</h4>
                            <hr>
                            
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $company->owner->name) }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">email</label>
                                <input type="text" class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $company->owner->email) }}" name="email">
                                @error('email')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="text" class="form-control @error('password') is-invalid @enderror"
                                    value="{{ old('password') }}" name="password">
                                @error('password')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label> 
                                <select class="form-select @error('status') is-invalid @enderror" name="status" required >
                                    <option value="">Select Status</option>
                                    
                                    <option value="active" {{$company->owner->status === 'active' ? 'selected':''}}>Active</option>
                                    <option value="inactive" {{$company->owner->status === 'inactive' ? 'selected':''}}>inactive</option>
                                </select>
                            </div>

                        </div> --}}
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <ul class="list-inline me-auto mb-0">
                        <li class="list-inline-item align-bottom">
                            <a href="{{ route('companies.delete', $company->id) }}"
                                class="avtar avtar-s btn-link-danger w-sm-auto" data-bs-toggle="tooltip" title="Delete">
                                <i class="ti ti-trash f-18"></i>
                            </a>
                        </li>
                    </ul>
                    <div class="flex-grow-1 text-end">
                        <button type="button" class="btn btn-link-danger" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>
