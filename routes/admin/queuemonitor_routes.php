<?php

/*
|--------------------------------------------------------------------------
| RECEPTION QUEUE MONITOR
|--------------------------------------------------------------------------
| A big-screen display for the front desk: today's token queue in
| waiting / called / in-consult stages with a polling refresh.
*/

use Illuminate\Support\Facades\Route;

Route::get('/queue-monitor', App\Http\Livewire\Admins\QueueMonitor::class)->name('admin_queue_monitor');