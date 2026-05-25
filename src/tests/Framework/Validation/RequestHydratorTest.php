<?php

declare(strict_types=1);

namespace Tests\Framework\Validation;

use Application\Dto\LoginRequest;
use Application\Dto\VerifyTwoFactorRequest;
use Framework\Validation\RequestHydrator;
use Framework\Validation\Validatable;
use Framework\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

final class RequestHydratorTest extends TestCase
{
    private RequestHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new RequestHydrator();
    }

    public function testHydratesValidLoginPayload(): void
    {
        $dto = $this->hydrator->hydrate(LoginRequest::class, [
            'email' => 'alice@example.com',
            'password' => 'hunter2',
        ]);

        $this->assertInstanceOf(LoginRequest::class, $dto);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertSame('hunter2', $dto->password);
    }

    public function testMissingRequiredFieldsAreReported(): void
    {
        $exception = null;
        try {
            $this->hydrator->hydrate(LoginRequest::class, []);
        } catch (ValidationException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame(422, $exception->getCode());
        $errors = $exception->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertSame('Email is required.', $errors['email'][0]);
        $this->assertSame('Password is required.', $errors['password'][0]);
    }

    public function testInvalidEmailFormatIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->hydrator->hydrate(LoginRequest::class, [
                'email' => 'not-an-email',
                'password' => 'hunter2',
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->getErrors());
            $this->assertSame('Email must be a valid address.', $e->getErrors()['email'][0]);
            throw $e;
        }
    }

    public function testWrongScalarTypeReportsTypeError(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->hydrator->hydrate(LoginRequest::class, [
                'email' => ['nested'],
                'password' => 'hunter2',
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->getErrors());
            $this->assertStringContainsString('type string', $e->getErrors()['email'][0]);
            throw $e;
        }
    }

    public function testVerifyTwoFactorRequestRespectsMinLength(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->hydrator->hydrate(VerifyTwoFactorRequest::class, [
                'challenge_token' => 'token',
                'code' => '12',
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
            throw $e;
        }
    }

    public function testNonValidatableTargetIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line - intentional misuse */
        $this->hydrator->hydrate(\stdClass::class, []);
    }

    public function testExtraPayloadKeysAreIgnored(): void
    {
        $dto = $this->hydrator->hydrate(LoginRequest::class, [
            'email' => 'alice@example.com',
            'password' => 'hunter2',
            'unexpected' => 'ignored',
        ]);

        $this->assertSame('alice@example.com', $dto->email);
    }

    public function testCombinesMultipleConstraintsForSameField(): void
    {
        $exception = null;
        try {
            $this->hydrator->hydrate(LoginRequest::class, [
                'email' => '',
                'password' => 'hunter2',
            ]);
        } catch (ValidationException $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertArrayHasKey('email', $exception->getErrors());
    }

    public function testCustomDtoWithDefaultsHydratesFromPartialPayload(): void
    {
        $dto = $this->hydrator->hydrate(RequestHydratorTestSampleDto::class, [
            'name' => 'Alice',
        ]);
        $this->assertInstanceOf(RequestHydratorTestSampleDto::class, $dto);
        $this->assertSame('Alice', $dto->name);
        $this->assertSame(42, $dto->age);
    }
}

final class RequestHydratorTestSampleDto implements Validatable
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name = '',
        public readonly int $age = 42,
    ) {
    }
}
