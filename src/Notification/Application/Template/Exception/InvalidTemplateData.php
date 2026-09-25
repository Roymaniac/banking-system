<?php

declare(strict_types=1);

namespace Notification\Application\Template\Exception;

use InvalidArgumentException;
use Notification\Application\Template\EmailTemplate;

/** Reports missing template data without including secret values. */
final class InvalidTemplateData extends InvalidArgumentException
{
    /** @param list<string> $missingVariables */
    public static function missing(EmailTemplate $template, array $missingVariables): self
    {
        return new self(sprintf(
            'Template "%s" is missing required variables: %s.',
            $template->value,
            implode(', ', $missingVariables),
        ));
    }
}
