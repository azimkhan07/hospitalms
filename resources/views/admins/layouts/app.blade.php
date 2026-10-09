<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ config('app.name', 'HMS') }} &middot; Admin</title>

    <link href="{{ asset('assets/vendor/fontawesome/css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/solid.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/brands.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/flagiconcss/css/flag-icon.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/master.css') }}?v={{ filemtime(public_path('assets/css/master.css')) }}" rel="stylesheet">

    <style>
        .sa-panel #sidebar {
            background: #0b3c66;
            background-image: linear-gradient(180deg, #0b3c66 0%, #082c4d 100%);
            border-right: 1px solid #072742;
        }
        .sa-panel #sidebar .sidebar-brand {
            padding: .65rem .8rem !important;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        .sa-panel #sidebar .sidebar-brand a {
            color: #fff;
        }
        .sa-panel #sidebar ul.components li a {
            color: #cfe2f3;
        }
        .sa-panel #sidebar ul.components li a i {
            color: #8fc4ea;
        }
        .sa-panel #sidebar ul.components li a:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
            border-left-color: #4dabf7;
        }
        .sa-panel #sidebar ul.components li a.active {
            background: rgba(15,127,212,.3);
            color: #fff;
            border-left-color: #4dabf7;
            font-weight: 600;
        }
        .sa-panel #sidebar .sidebar-role {
            color: #cfe2f3;
        }
        /* submenu sits on the dark panel: give it its own opaque, readable palette */
        .sa-panel #sidebar ul.components ul.submenu {
            background: #093054;
            box-shadow: inset 3px 0 0 rgba(255, 255, 255, .06);
        }
        .sa-panel #sidebar ul.components ul.submenu li a {
            background: transparent;
            color: #d6e7f7;
        }
        .sa-panel #sidebar ul.components ul.submenu li a::before {
            background: #8fb6d8;
        }
        .sa-panel #sidebar ul.components ul.submenu li a:hover {
            background: rgba(255, 255, 255, .1);
            color: #fff;
        }
        .sa-panel #sidebar ul.components ul.submenu li a.active {
            background: rgba(15, 127, 212, .35);
            color: #fff;
        }
        .sa-panel #sidebar ul.components ul.submenu li a.active::before {
            background: #fff;
        }
    </style>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @livewireStyles
</head>

<body class="clinic_version sa-panel">

    @php
        $brand = hms_tenant_brand();
    @endphp

    <div class="wrapper">
        <nav id="sidebar">
            <div class="sidebar-brand">
                <a href="{{ route('admin_dashboard') }}" title="{{ $brand['name'] }}">
                    <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
                </a>
            </div>
            <ul class="list-unstyled components text-secondary">
                @foreach (hms_sidebar_tree() as $item)
                    @if (empty($item['children']))
                        @php
                            $url = hms_sidebar_url($item);
                            $isActive = request()->fullUrlIs($url) || request()->fullUrlIs($url.'/*');
                        @endphp
                        <li>
                            <a href="{{ $url }}" class="{{ $isActive ? 'active' : '' }}">
                                <i class="fas {{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @else
                        @php
                            $groupActive = false;
                            foreach ($item['children'] as $child) {
                                $childUrl = hms_sidebar_url($child);
                                if (request()->fullUrlIs($childUrl) || request()->fullUrlIs($childUrl.'/*')) {
                                    $groupActive = true;
                                    break;
                                }
                            }
                        @endphp
                        <li class="nav-group {{ $groupActive ? 'open' : '' }}">
                            <a href="javascript:void(0)" class="nav-group-toggle {{ $groupActive ? 'active' : '' }}">
                                <span class="d-flex align-items-center" style="gap:.45rem">
                                    <i class="fas {{ $item['icon'] }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                </span>
                                <i class="fas fa-chevron-right nav-caret"></i>
                            </a>
                            <ul class="submenu">
                                @foreach ($item['children'] as $child)
                                    @php
                                        $childUrl = hms_sidebar_url($child);
                                        $childActive = request()->fullUrlIs($childUrl) || request()->fullUrlIs($childUrl.'/*');
                                    @endphp
                                    <li>
                                        <a href="{{ $childUrl }}" class="{{ $childActive ? 'active' : '' }}">
                                            <span>{{ $child['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div id="body">
            <nav class="navbar navbar-expand-lg fixed-top navbar-white bg-white">
                <button type="button" id="sidebarCollapse" class="btn btn-light">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="nav navbar-nav ml-auto">
                        @auth
                            @if (hms_can('meetings'))
                                <livewire:admins.meeting-bell />
                            @endif
                            <livewire:admins.notification-bell />
                        @endauth
                        <li class="nav-item dropdown">
                            <div class="nav-dropdown">
                                <a href="#" class="nav-item nav-link dropdown-toggle text-secondary"
                                    data-toggle="dropdown">
                                    <i class="fas fa-user"></i>
                                    <span>{{ auth()->user()->name ?? '' }}</span>
                                    <i style="font-size: .8em;" class="fas fa-caret-down"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right nav-link-menu">
                                    <ul class="nav-list">
                                        <li>
                                            <span class="dropdown-item">
                                                <i class="fas fa-id-badge"></i> {{ hms_role_label() }}
                                            </span>
                                        </li>
                                        <div class="dropdown-divider"></div>
                                        <li>
                                            <form method="POST" action="{{ route('logout') }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item logout-button">
                                                    <i class="fas fa-sign-out-alt"></i> Logout
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="content">
                <div class="container">
                    @isset($slot){{ $slot }}@endisset
                    @yield('admin_content')
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