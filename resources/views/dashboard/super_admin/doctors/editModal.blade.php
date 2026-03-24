<form action="{{ route('doctors.update', $doctor->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="modal fade" id="editModal_{{ $doctor->id }}" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="mb-0">Edit Doctor:{{ $doctor->name }}</h5>
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
                                    value="{{ old('name', $doctor->name) }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Specialty</label>
                                <select class="form-select @error('specialty_id') is-invalid @enderror"
                                    name="specialty_id" required>
                                    <option>Select Specialty</option>
                                    @if (!empty($doctor->specialty))
                                        @foreach ($specialties as $specialty)
                                            <option value="{{ $specialty->id }}"
                                                {{ $doctor->specialty->id == $specialty->id ? 'selected' : '' }}>
                                                {{ $doctor->specialty->name }}</option>
                                        @endforeach
                                    @else
                                        @foreach ($specialties as $specialty)
                                            <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                                        @endforeach
                                    @endif

                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $doctor->email) }}" name="email">
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
                                    value="{{ old('phone', $doctor->phone) }}" name="phone">
                                @error('phone')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Address 1</label>
                                <input type="text" class="form-control @error('address_1') is-invalid @enderror"
                                    value="{{ old('address_1', $doctor->address_1) }}" name="address_1">
                                @error('area')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            {{-- <div class="form-group">
                                <label class="form-label">Address 2 (optional)</label>
                                <input type="text" class="form-control @error('address_2') is-invalid @enderror"
                                    value="{{ old('address_2', $doctor->address_2) }}" name="address_2">
                                @error('area')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div> --}}


                        </div>

                        {{-- <div class="col-sm-6">
                            <h4>Availables Time</h4>
                            <hr>
                            @foreach ($doctor->availableTimes as $index => $time)
                                <div class="form-group">
                                    <label class="form-label">Available Date </label>
                                    <input type="date"
                                        class="form-control @error('date.' . $index) is-invalid @enderror"
                                        value="{{ old('date.' . $index, $time->date) }}"
                                        name="date[]">
                                    @error('date.' . $index)
                                        <div class="alert alert-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Start Time </label>
                                    <input class="form-control @error('start_time.' . $index) is-invalid @enderror"
                                        type="time" value="{{ old('start_time.' . $index, $time->start_time) }}"
                                        name="start_time[]">
                                    @error('start_time.' . $index)
                                        <div class="alert alert-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label">End Time </label>
                                    <input class="form-control @error('end_time.' . $index) is-invalid @enderror"
                                        type="time" value="{{ old('end_time.' . $index, $time->end_time) }}"
                                        name="end_time[]">
                                    @error('end_time.' . $index)
                                        <div class="alert alert-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <hr>
                            @endforeach

                        </div> --}}
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <ul class="list-inline me-auto mb-0">
                        <li class="list-inline-item align-bottom">
                            <a href="{{ route('doctors.delete', $doctor->id) }}"
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
