<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/v1/auth/2fa/verify` body.
 */
#[OA\Schema(
    schema: 'VerifyTwoFactorRequest',
    required: ['challenge_token', 'code'],
    description: '2FA verification challenge response. The `challenge_token` is the short-lived JWT issued by `/auth/login` when a 2FA-enabled user signs in.',
)]
final class VerifyTwoFactorRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'challenge_token is required.')]
        #[OA\Property(type: 'string', description: 'Challenge JWT from the login response.')]
        public readonly string $challenge_token = '',
        #[Assert\NotBlank(message: 'code is required.')]
        #[Assert\Length(min: 4, max: 10, exactMessage: '', minMessage: 'code must be at least {{ limit }} characters.', maxMessage: 'code must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', minLength: 4, maxLength: 10, example: '123456', description: 'TOTP code from the authenticator app.')]
        public readonly string $code = '',
    ) {
    }
}
