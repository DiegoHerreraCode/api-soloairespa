<?php

namespace App\Providers;

use App\Mail\Transport\SendGridApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class SendGridServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Mail::extend('sendgrid', function (array $config = []) {
            $key = $config['key'] ?? config('services.sendgrid.key') ?? env('SENDGRID_API_KEY');

            return new SendGridApiTransport((string) $key);
        });
    }
}
