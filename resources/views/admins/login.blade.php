<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Responsive Admin &amp; Dashboard Template based on Bootstrap 5">
    <meta name="author" content="AdminKit">
    <meta name="keywords"
        content="adminkit, bootstrap, bootstrap 5, admin, dashboard, template, responsive, css, sass, html, theme, front-end, ui kit, web">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" href="{{ asset('admins/img/icons/icon-48x48.png') }}" />

    <link rel="canonical" href="https://demo-basic.adminkit.io/pages-sign-in.html" />

    <title>Sign In | AdminKit Demo</title>

    <link href="{{ asset('admins/css/app.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
</head>

<body>
    <main class="d-flex w-100">
        <div class="container d-flex flex-column">
            <div class="row vh-100">
                <div class="col-sm-10 col-md-8 col-lg-6 mx-auto d-table h-100">
                    <div class="d-table-cell align-middle">
                        @if (session('error'))
                            <p style="color:red">{{ session('error') }}</p>
                        @endif
                        <div class="text-center mt-4">
                            <h1 class="h2">Welcome back, Charles</h1>
                            <p class="lead">
                                Sign in to your account to continue
                            </p>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="m-sm-4">
                                    <div class="text-center">
                                        <img src="{{ asset('admins/img/avatars/avatar.jpg') }}" alt="Charles Hall"
                                            class="img-fluid rounded-circle" width="132" height="132" />
                                    </div>
                                    <form method="POST" action="{{ route('admin_login') }}" id="loginForm">
                                        @csrf
                                        <input type="hidden" name="latitude" id="latitude">
                                        <input type="hidden" name="longitude" id="longitude">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input class="form-control form-control-lg" type="email" name="email"
                                                value="{{ old('email') }}" placeholder="Enter your email" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input class="form-control form-control-lg" type="password" name="password"
                                                placeholder="Enter your password" />
                                            <small>
                                                <a href="index.html">Forgot password?</a>
                                            </small>
                                        </div>
                                        <div>
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" value="remember-me"
                                                    name="remember-me" checked>
                                                <span class="form-check-label">
                                                    Remember me next time
                                                </span>
                                            </label>
                                        </div>
                                        <div class="text-center mt-3">
                                            <button type="submit" class="btn btn-lg btn-primary" id="signInBtn">
                                                Sign in
                                            </button>
                                        </div>
                                    </form>
                                    <p class="text-muted text-center mt-3 mb-0" style="font-size:12px">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Staff are marked present from their sign-in time, so this page reads
                                        your location to confirm you are at the hospital. The admin may
                                        sign in from anywhere.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('admins/js/app.js') }}"></script>
    <script>
        // Send the coordinates along with the sign-in so the server can check
        // them against the hospital. If the browser refuses, the form still
        // submits and the server decides what to do.
        (function () {
            var btn = document.getElementById('signInBtn');
            var form = document.getElementById('loginForm');
            var lat = document.getElementById('latitude');
            var lng = document.getElementById('longitude');
            var asked = false;

            function locate(done) {
                if (!navigator.geolocation) {
                    return done();
                }
                asked = true;
                navigator.geolocation.getCurrentPosition(function (pos) {
                    lat.value = pos.coords.latitude.toFixed(7);
                    lng.value = pos.coords.longitude.toFixed(7);
                    done();
                }, function () {
                    done();
                }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
            }

            form.addEventListener('submit', function (e) {
                if (asked || lat.value) {
                    return;
                }
                e.preventDefault();
                btn.disabled = true;
                btn.textContent = 'Checking location...';
                locate(function () {
                    form.submit();
                });
            });
        })();
    </script>

</body>

</html>
