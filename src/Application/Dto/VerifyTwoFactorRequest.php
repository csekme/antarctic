<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/v1/auth/2fa/verify` body.
 */
final class VerifyTwoFactorRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'challenge_token is required.')]
        public readonly string $challenge_token = '',
        #[Assert\NotBlank(message: 'code is required.')]
        #[Assert\Length(min: 4, max: 10, exactMessage: '', minMessage: 'code must be at least {{ limit }} characters.', maxMessage: 'code must be at most {{ limit }} characters.')]
        public readonly string $code = '',
    ) {
    }
}
