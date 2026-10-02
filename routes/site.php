<?php

/*
|--------------------------------------------------------------------------
| PUBLIC SITE ROUTES
|--------------------------------------------------------------------------
| Marketing / public pages rendered by SiteController.
*/

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'index'])->name('site.index');

Route::get('/about', [SiteController::class, 'about'])->name('site.about');

Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');

Route::get('/docters', [SiteController::class, 'doctors'])->name('site.doctors');

Route::get('/services', [SiteController::class, 'services'])->name('site.services');
