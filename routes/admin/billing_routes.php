<?php

use Illuminate\Support\Facades\Route;

Route::get('/day-book', App\Http\Livewire\Admins\DayBook::class)->name('admin_day_book');
