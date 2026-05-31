<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'VerifyEmailRequest',
    required: ['token'],
    description: 'Email-verification payload. The `token` is the raw activation token delivered to the user (email or dev-flag response field).',
)]
final class VerifyEmailRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'token is required.')]
        #[Assert\Regex(pattern: '/^[a-f0-9]{32}$/', message: 'token must be 32 lowercase hex characters.')]
        #[OA\Property(type: 'string', minLength: 32, maxLength: 32, example: 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6')]
        public readonly string $token = '',
    ) {
    }
}
