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
        /* ---- Super Admin panel: dark sidebar + nav identity (scoped to .sa-panel) ---- */
        .sa-panel #sidebar {
            background: #0b3c66;
            background-image: linear-gradient(180deg, #0b3c66 0%, #082c4d 100%);
            border-right: 1px solid #072742;
        }

        .sa-panel #sidebar .sidebar-brand {
            padding: .65rem .8rem !important;
            border-bottom: 1px solid rgba(255, 255, 255, .12);
        }

        .sa-panel #sidebar .sidebar-brand a {
            display: flex;
            align-items: center;
            gap: .55rem;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .sa-panel #sidebar .sidebar-brand a i {
            color: #7cc2ff;
            font-size: 15px;
        }

        .sa-panel #sidebar .sidebar-brand a small {
            display: block;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #9dc6e9;
        }

        .sa-panel #sidebar ul.components {
            padding: .4rem 0 1rem !important;
        }

        .sa-panel #sidebar ul.components li a {
            color: #cfe2f3;
            padding: .5rem .8rem !important;
            font-size: 13px;
            border-left: 3px solid transparent;
        }

        .sa-panel #sidebar ul.components li a i {
            color: #8fc4ea;
        }

        .sa-panel #sidebar ul.components li a:hover {
            background: rgba(255, 255, 255, .08);
            border-left-color: #4dabf7;
            color: #fff;
        }

        .sa-panel #sidebar ul.components li a.active {
            background: rgba(15, 127, 212, .3);
            border-left-color: #4dabf7;
            color: #fff;
            font-weight: 600;
        }

        .sa-panel #body .navbar {
            min-height: 44px;
            background: #fff;
            border-bottom: 1px solid #e3ebf3;
        }

        .sa-page-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #0b3c66;
            margin-left: .55rem;
            white-space: nowrap;
        }

        .sa-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: #0b3c66;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .sa-user {
            font-size: 12px;
            color: #61748a;
            margin-left: .5rem;
            white-space: nowrap;
        }

        .sa-panel #body .navbar .btn-light {
            color: #0b3c66;
            background: #eef4fb;
            border: 1px solid #e3ebf3;
        }

        .sa-panel #body .navbar .btn-light:hover {
            background: #e3ebf3;
        }

        .sa-stat {
            border: 1px solid #e3ebf3;
            border-radius: 8px;
            padding: 14px 16px;
            background: #fff;
            height: 100%;
        }

        .sa-stat .value {
            font-size: 24px;
            font-weight: 600;
            color: #0b3c66;
            line-height: 1.15;
        }

        .sa-stat .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #61748a;
        }
    </style>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @livewireStyles
</head>

<body class="clinic_version sa-panel">

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-brand">
                <a href="{{ route('superadmin.dashboard') }}">
                    <i class="fas fa-shield-halved"></i>
                    <span>HMS Console<small>Platform</small></span>
                </a>
            </div>
            <ul class="list-unstyled components">
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
        </nav>

        <div id="body">
            <nav class="navbar navbar-expand-lg fixed-top navbar-white bg-white">
                <button type="button" id="sidebarCollapse" class="btn btn-light">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="sa-page-title">{{ $title }}</span>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ml-auto">
                        <li class="nav-item d-flex align-items-center">
                            <span class="sa-badge"><i class="fas fa-user-shield"></i> Super Admin</span>
                            <span class="sa-user d-none d-md-inline">{{ auth()->user()->name ?? '' }}</span>
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