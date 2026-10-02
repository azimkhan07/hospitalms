<?php

use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/site', [SiteController::class, 'index'])->name('site.index');
Route::get('/doctors', [SiteController::class, 'doctors'])->name('site.doctors');
Route::get('/departments', [SiteController::class, 'departments'])->name('site.departments');
