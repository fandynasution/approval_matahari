<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
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
        // Semua email yang dikirim aplikasi selalu di-BCC ke noreply
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $bcc = 'noreply@matahariland.com';
            $message = $event->message;

            $existing = array_merge($message->getTo(), $message->getCc(), $message->getBcc());
            foreach ($existing as $address) {
                if (strcasecmp($address->getAddress(), $bcc) === 0) {
                    return;
                }
            }

            $message->addBcc($bcc);
        });
    }
}
