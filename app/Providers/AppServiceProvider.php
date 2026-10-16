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
        // Semua email yang dikirim aplikasi di-BCC ke noreply (config mail.bcc).
        // Jika MAIL_BCC_ENABLED=false, semua BCC dibuang agar tidak terkirim.
        // Jika MAIL_CC_ENABLED=false, semua CC dibuang agar tidak terkirim.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;

            if (!config('mail.cc.enabled')) {
                $message->getHeaders()->remove('Cc');
            }

            if (!config('mail.bcc.enabled')) {
                $message->getHeaders()->remove('Bcc');
                return;
            }

            $bcc = config('mail.bcc.address');

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
