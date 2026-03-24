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
                    
                </ul>
            @endif
            {{-- @if (Auth::user()->isAdmin())
            <ul class="pc-navbar">
                <li class="pc-item">
                    <a href="#" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>

                <li class="pc-item">
                    <a href="{{ route('representatives.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Representatives</span>
                    </a>
                </li>

                



                <li class="pc-item pc-hasmenu">
                    <a href="#!" class="pc-link"><span class="pc-micon"><i class="ti ti-menu"></i></span><span
                            class="pc-mtext">Menu
                            levels</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
                    <ul class="pc-submenu">

                        <li class="pc-item pc-hasmenu">
                            <a href="#!" class="pc-link">Level 2.2<span class="pc-arrow"><i
                                        data-feather="chevron-right"></i></span></a>
                            <ul class="pc-submenu">
                                <li class="pc-item"><a class="pc-link" href="#!">Level 3.1</a></li>
                                <li class="pc-item"><a class="pc-link" href="#!">Level 3.2</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
                <li class="pc-item">
                    <a href="../other/sample-page.html" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-brand-chrome"></i></span>
                        <span class="pc-mtext">Sample page</span>
                    </a>
                </li>
            </ul>
            @endif --}}

        </div>
    </div>
</nav>
<!-- [ Sidebar Menu ] end -->
