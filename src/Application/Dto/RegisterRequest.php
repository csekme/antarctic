<?php

declare(strict_types=1);

namespace Application\Dto;

use Framework\Validation\Validatable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['email', 'username', 'password', 'password_confirm'],
    description: 'User registration payload. The account is created with is_active=0; an email-verification step is required before login.',
)]
final class RegisterRequest implements Validatable
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Email must be a valid address.')]
        #[Assert\Length(max: 254, maxMessage: 'Email must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', format: 'email', maxLength: 254, example: 'alice@example.com')]
        public readonly string $email = '',
        #[Assert\NotBlank(message: 'Username is required.')]
        #[Assert\Length(min: 3, max: 45, minMessage: 'Username must be at least {{ limit }} characters.', maxMessage: 'Username must be at most {{ limit }} characters.')]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9_.\-]+$/', message: 'Username may only contain letters, digits, dot, dash and underscore.')]
        #[OA\Property(type: 'string', minLength: 3, maxLength: 45, example: 'alice')]
        public readonly string $username = '',
        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(min: 8, max: 255, minMessage: 'Password must be at least {{ limit }} characters.', maxMessage: 'Password must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', format: 'password', minLength: 8, maxLength: 255)]
        public readonly string $password = '',
        #[Assert\NotBlank(message: 'password_confirm is required.')]
        #[OA\Property(type: 'string', format: 'password', minLength: 8, maxLength: 255)]
        public readonly string $password_confirm = '',
        #[Assert\Length(max: 45, maxMessage: 'firstname must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', maxLength: 45, nullable: true)]
        public readonly ?string $firstname = null,
        #[Assert\Length(max: 45, maxMessage: 'lastname must be at most {{ limit }} characters.')]
        #[OA\Property(type: 'string', maxLength: 45, nullable: true)]
        public readonly ?string $lastname = null,
    ) {
    }

    #[Assert\Callback]
    public function validatePasswordsMatch(ExecutionContextInterface $context): void
    {
        if ($this->password !== '' && $this->password !== $this->password_confirm) {
            $context->buildViolation('Passwords do not match.')
                ->atPath('password_confirm')
                ->addViolation();
        }
    }
}
