<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Laravel ships Tailwind pagination markup, but every panel in this app
        // is Bootstrap 4 (AdminLTE) or the compact theme -- so the pager came
        // out unstyled and collapsed to zero height. Render the Bootstrap 4
        // markup instead, which matches the CSS that is actually loaded.
        Paginator::useBootstrapFour();
    }
}
