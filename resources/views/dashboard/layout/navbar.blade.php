<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="javascript:void(0);" class="b-brand text-primary">
                <!-- ========   Change your logo from here   ============ logo-lg-->
                <img src="{{ asset('assets/images/landing/client-05.png') }}" class="img-fluid" alt="logo">
                {{-- <h3>MED - Docotorz</h3> --}}
            </a>
        </div>
        <div class="navbar-content">
            @if (Auth::user()->isSuperAdmin())
                <ul class="pc-navbar">
                    <li class="pc-item">
                        <a href="{{ route('superadmin.dashboard') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>

                    <li class="pc-item">
                        <a href="{{ route('packages.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-package"></i></span>
                            <span class="pc-mtext">Packages</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('banner-ads.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-ad"></i></span>
                            <span class="pc-mtext">Banner Ads</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('companies.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-building-skyscraper"></i></span>
                            <span class="pc-mtext">Companies</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('specialties.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-arrows-split-2"></i></span>
                            <span class="pc-mtext">Specialities</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('doctors.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-heart"></i></span>
                            <span class="pc-mtext">Doctors</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('visits.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-hierarchy-2"></i></span>
                            <span class="pc-mtext">Visitis</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('notification-broadcasts.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-bell"></i></span>
                            <span class="pc-mtext">Push Notifications</span>
                        </a>
                    </li>

                </ul>
            @endif
            @if (Auth::user()->isAdmin())
                <ul class="pc-navbar">
                    <li class="pc-item">
                        <a href="{{ route('admin.dashboard') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                            <span class="pc-mtext">Dashboard</span>
                        </a>
                    </li>
                    <li class="pc-item">
                        <a href="{{ route('admin.push-notifications.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-bell"></i></span>
                            <span class="pc-mtext">Push Notifications</span>
                        </a>
                    </li>
                </ul>
            @endif

        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->
