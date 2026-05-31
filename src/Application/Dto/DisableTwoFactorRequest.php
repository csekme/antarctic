<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'DisableTwoFactorRequest',
    required: ['password'],
    description: 'Disable an active TOTP enrollment. Requires a fresh password to defeat hijacked-session abuse.',
)]
final class DisableTwoFactorRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'password is required.')]
        #[Assert\Length(max: 255, maxMessage: 'password must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', format: 'password', maxLength: 255)]
        public readonly string $password = '',
    ) {
    }
}
