<?php

namespace Lelevar\Sms;

use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('sms', function ($app) {
            return new SmsService();
        });

        $this->app->singleton('maxRudeness', function ($app) {
            return new MaxRudeness();
        });
    }

    public function boot()
    {
        // Register the facade aliases
        $this->app->alias('maxRudeness', Facades\MaxRudeness::class);
        $this->app->alias('sms', Facades\SmsService::class);
        // Register the helper functions
        if (file_exists($file = __DIR__ . '/helpers.php')) {
            require $file;
        }
    }
}
