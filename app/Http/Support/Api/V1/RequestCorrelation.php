<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use Illuminate\Http\Request;
use Shared\Domain\Identifier\CorrelationId;

/** Converts the request trace identifier into domain-event correlation metadata. */
final class RequestCorrelation
{
    public function from(Request $request): CorrelationId
    {
        return new CorrelationId((string) $request->attributes->get('request_id'));
    }
}
