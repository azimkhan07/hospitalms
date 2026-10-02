<?php

/*
|--------------------------------------------------------------------------
| AUTH ROUTES (staff / tenant-admin)
|--------------------------------------------------------------------------
| Every non-super-admin signs in at /login. The GET/POST /login routes are
| declared after Auth::routes() on purpose so they replace the default
| Laravel login form while keeping the rest of the auth scaffolding.
*/

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::get('/login', [AdminController::class, 'index'])->name('admin_login_form');

Route::post('/login', [AdminController::class, 'authenticate_admin'])->name('admin_login');
