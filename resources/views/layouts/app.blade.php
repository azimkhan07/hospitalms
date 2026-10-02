<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $site = \App\Models\SiteContent::get();
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $site['name'] }} | {{ $site['tagline'] }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Mobile Metas -->
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- Site Icons -->
    <link rel="shortcut icon" href="{{ storage_url($site['icon'], 'default.png') }}" type="image/x-icon" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <!-- Site CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <!-- Colors CSS -->
    <link rel="stylesheet" href="{{ asset('css/colors.css') }}">
    <!-- ALL VERSION CSS -->
    <link rel="stylesheet" href="{{ asset('css/versions.css') }}">
    <!-- Responsive CSS -->
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <!-- Modernizer for Portfolio -->
    <script src="{{ asset('js/modernizer.js') }}"></script>
    <!-- [if lt IE 9] -->
    @livewireStyles
</head>

<body class="clinic_version">


    <!-- LOADER -->
    <div id="preloader">
        <a href="{{ url('/') }}"><img class="preloader" src="{{ asset('images/loaders/heart-loading2.gif') }}"
                alt=""></a>
    </div>
    <!-- END LOADER -->
    <header>
        <div class="header-top wow fadeIn">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}"><img
                        src="{{ storage_url($site['logo'], 'default.png') }}" alt="{{ $site['name'] }}"></a>
                <div class="right-header">
                    <div class="header-info">
                        <div class="info-inner">
                            <span class="icontop"><img src="{{ asset('images/phone-icon.png') }}"
                                    alt="#"></span>
                            <span class="iconcont"><a href="tel:{{ $site['phone'] ?? '123 123 123' }}">{{ $site['phone'] ?? '123 123 123' }}</a></span>
                        </div>
                        <div class="info-inner">
                            <span class="icontop"><i class="fa fa-envelope" aria-hidden="true"></i></span>
                            <span class="iconcont"><a data-scroll
                                    href="mailto:{{ $site['email'] ?? 'info@hospital.test' }}">{{ $site['email'] ?? 'info@hospital.test' }}</a></span>
                        </div>
                        <div class="info-inner">
                            <span class="icontop"><i class="fa fa-clock-o" aria-hidden="true"></i></span>
                            <span class="iconcont"><a data-scroll href="#">Daily:
                                    {{ $site['working_hours'] }}</a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="header-bottom wow fadeIn">
            <div class="container-fluid">
                <nav class="main-menu">
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse"
                            data-target="#navbar" aria-expanded="false" aria-controls="navbar"><i class="fa fa-bars"
                                aria-hidden="true"></i></button>
                    </div>

                    <div id="navbar" class="navbar-collapse collapse">
                        <ul class="nav navbar-nav">
                            <li onclick="check_active('Home')"><a id="Home" href="{{ url('/') }}">Home</a>
                            </li>
                            <li onclick="check_active('Services')"><a id="Services" data-scroll
                                    href="{{ url('/services') }}">Services</a></li>
                            <li onclick="check_active('Doctors')"><a id="Doctors" data-scroll
                                    href="{{ url('docters') }}">Doctors</a></li>
                            <li onclick="check_active('Departments')"><a id="Departments" data-scroll
                                    href="{{ url('/#departments') }}">Departments</a></li>
                            <li onclick="check_active('About')"><a id="About" data-scroll
                                    href="{{ url('/about') }}">About us</a></li>
                            <li onclick="check_active('Contact')"><a id="Contact" data-scroll
                                    href="{{ url('contact') }}">Contact</a></li>
                            @auth
                                @if (auth()->user()->is_super_admin)
                                    <li onclick="check_active('admin-area')"><a id="admin-area" data-scroll
                                            href="{{ route('admin_dashboard') }}">Admin Area</a></li>
                                @endif
                            @endauth
                        </ul>
                    </div>
                </nav>
                @livewire('search')
            </div>
        </div>
    </header>
    <main id="main">
        @yield('content')
    </main>
    <a href="#home" data-scroll class="dmtop global-radius"><i class="fa fa-angle-up"></i></a>
    <footer id="footer" class="footer-area wow fadeIn">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="logo padding">
                        <a href="{{ url('/') }}">
                            <img src="{{ storage_url($site['logo'], 'default.png') }}" alt="{{ $site['name'] }}">
                        </a>
                        <p>{{ $site['about'] ?? $site['name'] . ' provides multi-speciality care with 24x7 emergency support.' }}
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="footer-info padding">
                        <h3>CONTACT US</h3>
                        <p><i class="fa fa-map-marker"
                                aria-hidden="true"></i>{{ $site['address'] ?? 'Hospital address' }}
                        </p>
                        <p><i class="fa fa-paper-plane"
                                aria-hidden="true"></i>{{ $site['email'] ?? 'info@hospital.test' }}
                        </p>
                        <p><i class="fa fa-phone" aria-hidden="true"></i>{{ $site['phone'] ?? '123 123 123' }}
                        </p>
                    </div>
                </div>
                @livewire('subscribe')
            </div>
        </div>
    </footer>
    <div class="copyright-area wow fadeIn">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="footer-text">
                        <p>&copy; {{ date('Y') }} <a id="tw" href="{{ url('/') }}"
                                target="_blank">{{ $site['name'] }}</a>. All rights reserved.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="social">
                        <ul class="social-links">
                            <li><a href="{{ $site['facebook'] }}"><i class="fa fa-facebook"></i></a></li>
                            <li><a href="{{ $site['twitter'] }}"><i class="fa fa-twitter"></i></a></li>
                            <li><a href="{{ $site['linkedin'] }}"><i class="fa fa-linkedin"></i></a></li>
                            <li><a href="{{ $site['instagram'] }}"><i class="fa fa-instagram"></i></a></li>
                            <li><a href="{{ $site['youtube'] }}"><i class="fa fa-youtube"></i></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end copyrights -->
    @livewireScripts
    <!-- all js files -->
    <script src="{{ asset('js/all-in-one.js') }}"></script>
    <!-- all plugins -->
    <script src="{{ asset('js/custom.js') }}"></script>
    <script>
        function check_active(d) {
            document.getElementById(d).classList.add('active');
        }
    </script>
</body>

</html>