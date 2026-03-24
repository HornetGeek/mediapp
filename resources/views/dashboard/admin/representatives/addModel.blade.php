<form action="{{ route('representatives.store') }}" method="POST">
    @csrf

    
    <div class="modal fade" id="addModal" data-bs-keyboard="false" tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="mb-0">Add Representative</h5>
                    <a href="#" class="avtar avtar-s btn-link-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x f-20"></i>
                    </a>
                </div>

                <div class="modal-body">

                    <div class="row">
                        <div class="col-sm-12">
                            <h4>Representative Info</h4>
                            <hr>

                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror"
                                    name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">inactive</option>

                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" name="email">
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
                                    value="{{ old('phone') }}" name="phone">
                                @error('phone')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control @error('country') is-invalid @enderror"
                                    value="{{ old('country') }}" name="country">
                                @error('country')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror"
                                    value="{{ old('city') }}" name="city">
                                @error('city')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label">Area</label>
                                <input type="text" class="form-control @error('area') is-invalid @enderror"
                                    value="{{ old('area') }}" name="area">
                                @error('area')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                        </div>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    
                    <div class="flex-grow-1 text-end">
                        <button type="button" class="btn btn-link-danger"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"
                            data-bs-dismiss="modal">Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>