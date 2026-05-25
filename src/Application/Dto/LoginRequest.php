<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/v1/auth/login` body.
 */
final class LoginRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid address.')]
        #[Assert\Length(max: 254, maxMessage: 'Email must be at most {{ limit }} characters.')]
        public readonly string $email = '',
        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(min: 1, max: 255, maxMessage: 'Password must be at most {{ limit }} characters.')]
        public readonly string $password = '',
    ) {
    }
}
