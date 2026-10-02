<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Loaded by bootstrap/app.php with the "api" middleware group and the "api"
| prefix. Each module keeps its own file under routes/api so the surface stays
| mirror-image with the web modules (site, auth, admin, superadmin).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/site.php';
    require __DIR__.'/api/appointments.php';
    require __DIR__.'/api/admin.php';
    require __DIR__.'/api/superadmin.php';
});
