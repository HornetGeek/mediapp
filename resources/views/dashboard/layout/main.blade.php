<!DOCTYPE html>
<html lang="en">
<!-- [Head] start -->

<head>
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- [Favicon] icon -->
    <link rel="icon" href="{{ asset('assets/images/favicon.svg') }}" type="image/x-icon">
    <!-- [Google Font] Family -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        id="main-font-link">
    <!-- [Tabler Icons] https://tablericons.com -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <!-- [Feather Icons] https://feathericons.com -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <!-- [Font Awesome Icons] https://fontawesome.com/icons -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <!-- [Material Icons] https://fonts.google.com/icons -->
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">
    <!-- [Template CSS Files] -->
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
    <link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}">
    <title>@yield('title', 'Home')</title>
</head>
<!-- [Head] end -->
<!-- [Body] Start -->

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- [ Sidebar Menu ] start -->
    @include('dashboard.layout.navbar')
    <!-- [ Sidebar Menu ] end -->


    <!-- [ Header Topbar ] start -->
    @include('dashboard.layout.header')
    <!-- [ Header ] end -->



    <!-- [ Main Content ] start -->
    <div class="pc-container">
        @yield('content')
    </div>
    <!-- [ Main Content ] end -->



    <!-- [ Footer ] start -->
    @include('dashboard.layout.footer')
    <!-- [ Footer ] end -->

    <!-- [Page Specific JS] start -->
    {{-- <script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script> --}}
    {{-- <script src="{{ asset('assets/js/pages/dashboard-default.js') }}"></script> --}}
    <!-- [Page Specific JS] end -->
    <!-- Required Js -->
    <script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/fonts/custom-font.js') }}"></script>
    <script src="{{ asset('assets/js/pcoded.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/feather.min.js') }}"></script>
    <script src="{{asset('assets/js/plugins/simple-datatables.js')}}"></script>
    <script>
        document.querySelectorAll('table[data-client-datatable="true"]').forEach((table) => {
            new simpleDatatables.DataTable(table, {
                sortable: false,
                perPage: 5
            });
        });
    </script>




    <script>
        layout_change('light');
    </script>




    <script>
        change_box_container('false');
    </script>



    <script>
        layout_rtl_change('false');
    </script>


    <script>
        preset_change("preset-1");
    </script>


    <script>
        font_change("Public-Sans");
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @yield('js')

</body>
<!-- [Body] end -->

</html>
