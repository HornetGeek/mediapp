@extends('dashboard.layout.main')
@section('title', 'Banner Ads')
@section('content')
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('superadmin.dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item" aria-current="page">Banner Ads</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Banner Ads</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card table-card">
                    <div class="card-body">
                        <div class="text-end p-4 pb-0">
                            <a href="#" class="btn btn-primary d-inline-flex align-item-center" data-bs-toggle="modal"
                                data-bs-target="#addModal">
                                <i class="ti ti-plus f-18"></i> Add Banner
                            </a>
                        </div>

                        @include('dashboard.super_admin.banner_ads.addModel')

                        <div class="table-responsive">
                            <div class="datatable-wrapper datatable-loading no-footer searchable fixed-columns">
                                <div class="datatable-container">
                                    <table class="table table-hover datatable-table" id="pc-dt-simple" data-client-datatable="true">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Image</th>
                                                <th>Title</th>
                                                <th>Click URL</th>
                                                <th>Sort</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($bannerAds as $bannerAd)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        <img src="{{ $bannerAd->image_url }}" alt="{{ $bannerAd->title }}"
                                                            class="rounded" style="width: 140px; height: 64px; object-fit: cover;">
                                                    </td>
                                                    <td>
                                                        <h5 class="mb-0">{{ $bannerAd->title }}</h5>
                                                    </td>
                                                    <td>
                                                        @if ($bannerAd->click_url)
                                                            <a href="{{ $bannerAd->click_url }}" target="_blank" rel="noopener">
                                                                {{ $bannerAd->click_url }}
                                                            </a>
                                                        @else
                                                            ---
                                                        @endif
                                                    </td>
                                                    <td>{{ $bannerAd->sort_order }}</td>
                                                    <td>
                                                        <span class="badge {{ $bannerAd->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                                            {{ ucfirst($bannerAd->status) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <ul class="list-inline me-auto mb-0">
                                                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                                                <a href="#" class="avtar avtar-xs btn-link-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editModal_{{ $bannerAd->id }}">
                                                                    <i class="ti ti-edit-circle f-18"></i>
                                                                </a>
                                                            </li>
                                                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Delete">
                                                                <a href="#" class="avtar avtar-xs btn-link-danger"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteModal_{{ $bannerAd->id }}">
                                                                    <i class="ti ti-trash f-18"></i>
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </td>
                                                </tr>
                                                @include('dashboard.super_admin.banner_ads.editModal', ['bannerAd' => $bannerAd])
                                                @include('dashboard.super_admin.banner_ads.deleteModal', ['bannerAd' => $bannerAd])
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center">No banner ads found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            {{ $bannerAds->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

<style>
    .table tr th {
        width: auto;
        font-size: 14px;
    }
</style>
