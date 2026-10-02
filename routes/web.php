<?php

/*
|--------------------------------------------------------------------------
| WEB ROUTE LOADER
|--------------------------------------------------------------------------
| Routes are split per module. Each file is loaded inside the "web"
| middleware group (Laravel applies it to routes/web.php), so they all get
| sessions, CSRF and cookie handling automatically.
|
|   site.php        public marketing pages
|   auth.php        staff / tenant-admin login + Laravel auth scaffolding
|   admin.php       tenant admin & staff panel
|   superadmin.php  platform owner login + panel
|
| Add new web modules here instead of growing this file.
*/

require __DIR__.'/site.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/superadmin.php';
