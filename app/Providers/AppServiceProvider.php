<?php

namespace App\Providers;

use App\Contracts\MessageSender;
use App\Contracts\MessageTransport;
use App\Services\Messaging\HttpMessageTransport;
use App\Services\Messaging\LogMessageTransport;
use App\Services\Messaging\MessagingService;
use App\Services\PrintService;
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
        // Messaging: pick the transport from config so dev/test default to the
        // logging transport and production can hand over to a real gateway.
        $this->app->bind(MessageTransport::class, function () {
            return config('messaging.driver', 'log') === 'http'
                ? new HttpMessageTransport
                : new LogMessageTransport;
        });

        $this->app->bind(MessageSender::class, fn () => new MessagingService(
            app(MessageTransport::class)
        ));

        $this->app->singleton(PrintService::class);
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
