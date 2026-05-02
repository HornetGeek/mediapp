@extends('dashboard.layout.main')
@section('title', 'New Push Notification')
@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('notification-broadcasts.index') }}">Push Notifications</a></li>
                            <li class="breadcrumb-item" aria-current="page">New broadcast</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">New Push Notification</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('notification-broadcasts.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="form-group mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" maxlength="255"
                                    class="form-control @error('title') is-invalid @enderror"
                                    value="{{ old('title') }}" name="title" required>
                                @error('title')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Body</label>
                                <textarea name="body" rows="4" maxlength="2000"
                                    class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                                @error('body')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Image (optional)</label>
                                <input type="file" class="form-control @error('image') is-invalid @enderror"
                                    name="image" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted">JPG, PNG or WEBP up to 4 MB. Image is shown in the FCM popup.</small>
                                @error('image')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label d-block">Target</label>
                                <div class="form-check">
                                    <input type="radio" name="target_type" value="all" id="target_all"
                                        class="form-check-input" {{ old('target_type', 'all') === 'all' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="target_all">All doctors</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="target_type" value="specialties" id="target_specialties"
                                        class="form-check-input" {{ old('target_type') === 'specialties' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="target_specialties">By specialty</label>
                                </div>
                                @error('target_type')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3" id="specialties_block" style="display: none;">
                                <label class="form-label">Specialties</label>
                                <select name="specialty_ids[]" multiple class="form-select @error('specialty_ids') is-invalid @enderror" size="8">
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}"
                                            {{ in_array($specialty->id, (array) old('specialty_ids', [])) ? 'selected' : '' }}>
                                            {{ $specialty->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Hold Ctrl (or Cmd) to select multiple.</small>
                                @error('specialty_ids')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                                @error('specialty_ids.*')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="text-end">
                                <a href="{{ route('notification-broadcasts.index') }}" class="btn btn-link-danger">Cancel</a>
                                <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var allRadio = document.getElementById('target_all');
            var specialtiesRadio = document.getElementById('target_specialties');
            var block = document.getElementById('specialties_block');

            function refresh() {
                block.style.display = specialtiesRadio.checked ? '' : 'none';
            }

            allRadio.addEventListener('change', refresh);
            specialtiesRadio.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endsection
