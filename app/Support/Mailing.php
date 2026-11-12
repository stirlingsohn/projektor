<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends mail during a request and turns a failing mail server into an
 * error message next to the email field instead of a server error.
 */
class Mailing
{
    public static function send(callable $send): void
    {
        try {
            $send();
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => __('The email could not be sent. Please try again later.'),
            ]);
        }
    }
}
