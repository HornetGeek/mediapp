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
                                <label class="form-label">Delivery Type</label>
                                <select name="delivery_type" id="delivery_type"
                                    class="form-select @error('delivery_type') is-invalid @enderror" required>
                                    <option value="both" {{ old('delivery_type', 'both') === 'both' ? 'selected' : '' }}>Push + In-app</option>
                                    <option value="push_only" {{ old('delivery_type') === 'push_only' ? 'selected' : '' }}>Push only</option>
                                    <option value="in_app_only" {{ old('delivery_type') === 'in_app_only' ? 'selected' : '' }}>In-app only</option>
                                </select>
                                @error('delivery_type')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3" id="display_type_block">
                                <label class="form-label">Display Type</label>
                                <select name="display_type" id="display_type"
                                    class="form-select @error('display_type') is-invalid @enderror">
                                    <option value="list" {{ old('display_type', 'list') === 'list' ? 'selected' : '' }}>List</option>
                                    <option value="modal" {{ old('display_type') === 'modal' ? 'selected' : '' }}>Modal</option>
                                </select>
                                @error('display_type')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3" id="is_skippable_block">
                                <div class="form-check">
                                    <input type="hidden" name="is_skippable" value="0">
                                    <input type="checkbox" name="is_skippable" id="is_skippable" value="1"
                                        class="form-check-input" {{ old('is_skippable', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_skippable">Skippable</label>
                                </div>
                                <small class="text-muted">When unchecked, modal cannot be dismissed by the user.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Image (optional)</label>
                                <input type="file" class="form-control @error('image') is-invalid @enderror"
                                    name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <small class="text-muted">JPG, PNG or WEBP up to 4 MB. Image is shown in the FCM popup.</small>
                                @error('image')
                                    <div class="alert alert-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3" id="video_block">
                                <label class="form-label">Video (optional)</label>
                                <input type="file" class="form-control @error('video') is-invalid @enderror"
                                    name="video" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm">
                                <small class="text-muted">MP4/MOV/WEBM up to 30 MB, max 20 seconds. In-app only — disabled when delivery is "Push only".</small>
                                @error('video')
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
            var specialtiesBlock = document.getElementById('specialties_block');
            var deliverySelect = document.getElementById('delivery_type');
            var displaySelect = document.getElementById('display_type');
            var displayBlock = document.getElementById('display_type_block');
            var skippableBlock = document.getElementById('is_skippable_block');
            var videoBlock = document.getElementById('video_block');
            var videoInput = videoBlock ? videoBlock.querySelector('input[name="video"]') : null;

            function refreshTarget() {
                specialtiesBlock.style.display = specialtiesRadio.checked ? '' : 'none';
            }

            function refreshDeliveryDisplay() {
                var delivery = deliverySelect.value;

                if (delivery === 'push_only') {
                    displaySelect.value = 'list';
                    displayBlock.style.display = 'none';
                    if (videoInput) {
                        videoInput.value = '';
                    }
                    videoBlock.style.display = 'none';
                } else {
                    displayBlock.style.display = '';
                    videoBlock.style.display = '';
                }

                skippableBlock.style.display = displaySelect.value === 'modal' ? '' : 'none';
            }

            allRadio.addEventListener('change', refreshTarget);
            specialtiesRadio.addEventListener('change', refreshTarget);
            deliverySelect.addEventListener('change', refreshDeliveryDisplay);
            displaySelect.addEventListener('change', refreshDeliveryDisplay);

            refreshTarget();
            refreshDeliveryDisplay();
        })();
    </script>
@endsection
