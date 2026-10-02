<!doctype html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Login &middot; {{ config('app.name', 'HMS') }}</title>

    <link href="{{ asset('assets/vendor/fontawesome/css/fontawesome.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome/css/solid.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0b3c66, #0f7fd4);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .hms-sa-login {
            width: 100%;
            max-width: 380px;
            background: #fff;
            border-radius: 10px;
            padding: 32px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .25);
        }

        .hms-sa-login h1 {
            font-size: 20px;
            margin: 0 0 4px;
            color: #0b3c66;
        }

        .hms-sa-login p.sub {
            font-size: 13px;
            color: #61748a;
            margin-bottom: 22px;
        }

        .hms-sa-login label {
            font-size: 13px;
            color: #61748a;
        }

        .btn-hms {
            background: #0f7fd4;
            border-color: #0f7fd4;
            color: #fff;
        }

        .btn-hms:hover {
            background: #0b6cb4;
            border-color: #0b6cb4;
            color: #fff;
        }
    </style>
</head>

<body>
    <form class="hms-sa-login" method="POST" action="{{ route('superadmin.login.attempt') }}">
        @csrf

        <div class="text-center mb-3">
            <i class="fas fa-shield-halved fa-2x" style="color:#0f7fd4"></i>
        </div>

        <h1 class="text-center">Super Admin Console</h1>
        <p class="sub text-center">Platform owner access only</p>

        @if (session('error'))
            <div class="alert alert-danger py-2" style="font-size:13px">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
        @endif

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="form-control" required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control" required
                autocomplete="current-password">
        </div>

        <div class="form-group form-check">
            <input id="remember" type="checkbox" name="remember" value="1" class="form-check-input">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn btn-hms btn-block">
            <i class="fas fa-right-to-bracket"></i> Sign in
        </button>
    </form>
</body>

</html>
