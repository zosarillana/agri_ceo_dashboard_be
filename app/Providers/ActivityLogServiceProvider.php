<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function ($event) {
            activity('auth')
                ->causedBy($event->user)
                ->log('login');
        });

        Event::listen(Logout::class, function ($event) {
            activity('auth')
                ->causedBy($event->user)
                ->log('logout');
        });

        Event::listen(Failed::class, function ($event) {
            activity('auth')
                ->withProperties([
                    'email' => $event->credentials['email'] ?? null,
                ])
                ->log('failed_login');
        });

        Event::listen(PasswordReset::class, function ($event) {
            activity('auth')
                ->causedBy($event->user)
                ->log('password_reset');
        });
    }
}