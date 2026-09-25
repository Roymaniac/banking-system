<?php

declare(strict_types=1);

use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;

it('builds a normalized transport-independent email message', function (): void {
    $email = new EmailMessage(
        new RecipientEmail(' Customer@Example.COM '),
        new EmailSubject('Account opened'),
        new EmailBody('Your account is ready.'),
    );

    expect($email->recipient()->value())->toBe('customer@example.com')
        ->and($email->subject()->value())->toBe('Account opened')
        ->and($email->body()->value())->toBe('Your account is ready.');
});

it('rejects an invalid recipient address', function (): void {
    new RecipientEmail('not-an-email');
})->throws(InvalidArgumentException::class, 'valid recipient');

it('rejects subject header injection', function (): void {
    new EmailSubject("Statement ready\r\nBcc: attacker@example.com");
})->throws(InvalidArgumentException::class, 'single line');

it('rejects an empty email body', function (): void {
    new EmailBody("  \n ");
})->throws(InvalidArgumentException::class, 'cannot be empty');
