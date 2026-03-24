<form action="{{ route('doctors.store') }}" method="POST">
    @csrf
    <div class="modal fade" id="addModal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="mb-0">Add Doctor</h5>
                    <a href="#" class="avtar avtar-s btn-link-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x f-20"></i>
                    </a>
                </div>

                <div class="modal-body">

                    <div class="row">
                        <div class="col-sm-12">
                            <h4>Doctor Info</h4>
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
                                <label class="form-label">Specialty</label>
                                <select class="form-select @error('specialty_id') is-invalid @enderror"
                                    name="specialty_id" required>
                                    <option>Select Specialty</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                                    @endforeach
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

                            {{-- <div class="form-group">
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
                            </div> --}}

                            <div class="form-group">
                                <label class="form-label">Address 1</label>
                                <input type="text" class="form-control @error('address_1') is-invalid @enderror"
                                    value="{{ old('address_1') }}" name="address_1">
                                @error('area')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            {{-- <div class="form-group">
                                <label class="form-label">Address 2 (optional)</label>
                                <input type="text" class="form-control @error('address_2') is-invalid @enderror"
                                    value="{{ old('address_2') }}" name="address_2">
                                @error('area')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div> --}}

                        </div>
                        {{-- <div class="col-sm-6">
                            <h4>Availables Time</h4>
                            <hr>

                            <div id="available-times">
                                <div class="time-group row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Available Date</label>
                                        <input type="date" name="date[]" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" name="start_time[]" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">End Time</label>
                                        <input type="time" name="end_time[]" class="form-control" required>
                                    </div>
                                    <div class="form-group mt-2">
                                        <label class="form-label">Status</label>
                                        <select class="form-select @error('status') is-invalid @enderror"
                                            name="status[]" required>
                                            <option value="available">Available</option>
                                            <option value="canceled">Canceled</option>
                                            <option value="booked">Booked</option>
                                        </select>
                                    </div>

                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-time">
                                + Add Another Time
                            </button>
                        </div> --}}

                    </div>
                    <div class="modal-footer justify-content-between">

                        <div class="flex-grow-1 text-end">
                            <button type="button" class="btn btn-link-danger"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Save</button>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</form>
<script>
    document.getElementById('add-time').addEventListener('click', function() {
        const container = document.getElementById('available-times');
        const newGroup = document.createElement('div');
        newGroup.classList.add('time-group', 'row', 'mb-3', 'align-items-end');

        newGroup.innerHTML = `
            <div class="col-md-4">
                <label class="form-label">Available Date</label>
                <input type="date" name="date[]" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Start Time</label>
                <input type="time" name="start_time[]" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">End Time</label>
                <input type="time" name="end_time[]" class="form-control" required>
            </div>
            <div class="form-group mt-2">
                <label class="form-label">Status</label>
                <select class="form-select @error('status') is-invalid @enderror"
                    name="status[]" required>
                    <option value="available">Available</option>
                    <option value="canceled">Canceled</option>
                    <option value="booked">Booked</option>
                </select>
            </div>
            <br>
            <div class="col-md-12 mt-2">
                <button type="button" class="btn btn-danger btn-sm remove-time">
                    حذف
                </button>
            </div>
        `;

        container.appendChild(newGroup);
    });

    // استخدم event delegation للحذف
    document.getElementById('available-times').addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-time')) {
            e.target.closest('.time-group').remove();
        }
    });
</script>
