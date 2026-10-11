<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Operation;

use Account\Domain\Account\ValueObject\FreezeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Requires a controlled reason whenever staff restrict an account. */
final class FreezeAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', Rule::enum(FreezeReason::class)]];
    }
}
