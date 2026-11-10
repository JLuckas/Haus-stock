<?php

namespace App\Providers;

use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Relative dates ("há 5 minutos") follow the app language.
        Carbon::setLocale($this->app->getLocale());
        Event::listen(LocaleUpdated::class, fn (LocaleUpdated $event) => Carbon::setLocale($event->locale));
    }
}
