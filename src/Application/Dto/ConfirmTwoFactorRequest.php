<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'ConfirmTwoFactorRequest',
    required: ['code'],
    description: 'Activate a pending TOTP enrollment by submitting the first valid code from the authenticator app.',
)]
final class ConfirmTwoFactorRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'code is required.')]
        #[Assert\Regex(pattern: '/^[0-9]{6}$/', message: 'code must be a 6-digit number.')]
        #[OA\Property(type: 'string', minLength: 6, maxLength: 6, example: '123456')]
        public readonly string $code = '',
    ) {
    }
}
