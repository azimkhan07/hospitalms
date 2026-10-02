@props(['title' => 'Super Admin'])

<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title }} &middot; {{ config('app.name', 'HMS') }}</title>

    <link href="{{ asset('assets/vendor/fontawesome/css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/solid.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/brands.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/master.css') }}" rel="stylesheet">

    <style>
        .hms-sa-badge {
            display: inline-block;
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            background: #0b3c66;
            color: #fff;
            padding: 3px 9px;
            border-radius: 999px;
        }

        .hms-sa-sidebar-brand a {
            color: #fff;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .hms-sa-stat {
            border: 1px solid #e3ebf3;
            border-radius: 8px;
            padding: 16px;
            background: #fff;
            height: 100%;
        }

        .hms-sa-stat .value {
            font-size: 26px;
            font-weight: 600;
        }

        .hms-sa-stat .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #61748a;
        }
    </style>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @livewireStyles
</head>

<body class="clinic_version">

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-brand hms-sa-sidebar-brand">
                <a href="{{ route('superadmin.dashboard') }}">
                    <i class="fas fa-shield-halved"></i> HMS Console
                </a>
            </div>
            <ul class="list-unstyled components text-secondary">
                <li>
                    <a href="{{ route('superadmin.dashboard') }}"
                        class="{{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.tenants') }}"
                        class="{{ request()->routeIs('superadmin.tenants') ? 'active' : '' }}">
                        <i class="fas fa-hospital"></i>
                        <span>Hospitals &amp; Clinics</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.errors') }}"
                        class="{{ request()->routeIs('superadmin.errors') ? 'active' : '' }}">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span>Error Monitor</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-role">
                <i class="fas fa-user-shield"></i>
                <span class="hms-sa-badge">Super Admin</span>
            </div>
        </nav>

        <div id="body">
            <nav class="navbar navbar-expand-lg fixed-top navbar-white bg-white">
                <button type="button" id="sidebarCollapse" class="btn btn-light">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ml-auto">
                        <li class="nav-item">
                            <span class="nav-link text-secondary">
                                <i class="fas fa-user"></i> {{ auth()->user()->name ?? '' }}
                            </span>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('superadmin.logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-light">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="content">
                <div class="container">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    @livewireScripts

    <script>
        document.addEventListener('livewire:initialized', () => {
            setTimeout(() => {
                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(document.body);
                }
            }, 150);
        });
    </script>
</body>

</html>
