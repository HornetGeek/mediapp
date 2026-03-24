<form action="{{ route('packages.store') }}" method="POST">
    @csrf

    
    <div class="modal fade" id="addModal" data-bs-keyboard="false" tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="mb-0">Add Package</h5>
                    <a href="#" class="avtar avtar-s btn-link-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x f-20"></i>
                    </a>
                </div>

                <div class="modal-body">

                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price</label>
                                <input type="number"
                                    class="form-control @error('price') is-invalid @enderror"
                                    value="{{ old('price') }}" name="price">
                                @error('price')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Duration (in days)</label>
                                <input type="number"
                                    class="form-control @error('duration') is-invalid @enderror"
                                    value="{{ old('duration') }}" name="duration">
                                @error('duration')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter Description">{{old('description')}}</textarea>
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