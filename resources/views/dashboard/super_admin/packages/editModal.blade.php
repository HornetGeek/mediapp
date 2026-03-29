<form action="{{ route('packages.update', $package->id) }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="modal fade" id="editModal_{{$package->id}}" data-bs-keyboard="false" tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="mb-0">Edit Package:{{$package->name}}</h5>
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
                                    value="{{ old('name', $package->name) }}" name="name">
                                @error('name')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Price</label>
                                <input type="number"
                                    class="form-control @error('price') is-invalid @enderror"
                                    value="{{ old('price', $package->price) }}" name="price">
                                @error('price')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Subscription Plan</label>
                                <select class="form-select @error('plan_type') is-invalid @enderror" name="plan_type" required>
                                    <option value="">Select plan</option>
                                    <option value="quarterly" {{ old('plan_type', $package->resolvePlanType()) === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                    <option value="semi_annual" {{ old('plan_type', $package->resolvePlanType()) === 'semi_annual' ? 'selected' : '' }}>Semi-Annual</option>
                                    <option value="annual" {{ old('plan_type', $package->resolvePlanType()) === 'annual' ? 'selected' : '' }}>Annual</option>
                                </select>
                                @error('plan_type')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Enter Description">{{$package->description}}</textarea>
                            </div>

                        </div>
                    </div>

                </div>
                <div class="modal-footer justify-content-between">
                    <ul class="list-inline me-auto mb-0">
                        <li class="list-inline-item align-bottom">
                            <a href="{{route('packages.delete', $package->id)}}" class="avtar avtar-s btn-link-danger w-sm-auto"
                                data-bs-toggle="tooltip" title="Delete">
                                <i class="ti ti-trash f-18"></i>
                            </a>
                        </li>
                    </ul>
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
