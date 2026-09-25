<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Email;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Notification\Application\Email\EmailSender;
use Notification\Domain\Email\EmailMessage;
use Notification\Infrastructure\Email\Exception\EmailDeliveryFailed;
use Throwable;

/** Sends the transport-independent message through Laravel's configured mailer. */
final readonly class LaravelEmailSender implements EmailSender
{
    public function __construct(private Mailer $mailer) {}

    public function send(EmailMessage $email): void
    {
        try {
            $this->mailer->raw($email->body()->value(), function (Message $message) use ($email): void {
                $message->to($email->recipient()->value());
                $message->subject($email->subject()->value());
            });
        } catch (Throwable $exception) {
            // Keep transport details in the exception chain, not in the public message.
            throw EmailDeliveryFailed::because($exception);
        }
    }
}
