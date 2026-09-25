<?php

declare(strict_types=1);

use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Application\Template\Exception\InvalidTemplateData;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Infrastructure\Template\BladeEmailTemplateRenderer;
use Tests\TestCase;

uses(TestCase::class);

it('binds the template renderer contract to Blade', function (): void {
    expect(app(EmailTemplateRenderer::class))->toBeInstanceOf(BladeEmailTemplateRenderer::class);
});

it('renders the approved verification template as an email message', function (): void {
    $email = app(EmailTemplateRenderer::class)->render(
        EmailTemplate::EmailVerification,
        new RecipientEmail('customer@example.com'),
        [
            'verificationUrl' => 'https://bank.example/verify-email?token=secret-token',
            'expiresAt' => '2026-09-25 12:00 WAT',
        ],
    );

    expect($email->subject()->value())->toBe('Verify your email address')
        ->and($email->body()->value())->toContain('https://bank.example/verify-email?token=secret-token')
        ->and($email->body()->value())->toContain('2026-09-25 12:00 WAT');
});

it('rejects a template when required data is missing', function (): void {
    app(EmailTemplateRenderer::class)->render(
        EmailTemplate::PasswordReset,
        new RecipientEmail('customer@example.com'),
        ['expiresAt' => '2026-09-25 12:00 WAT'],
    );
})->throws(InvalidTemplateData::class, 'resetUrl');
