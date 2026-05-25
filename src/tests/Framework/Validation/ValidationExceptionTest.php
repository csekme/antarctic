<?php

declare(strict_types=1);

namespace Tests\Framework\Validation;

use Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class ValidationExceptionTest extends TestCase
{
    public function testExposesErrorsAndCode(): void
    {
        $errors = ['email' => ['Required.']];
        $exception = new ValidationException($errors);

        $this->assertSame(422, $exception->getCode());
        $this->assertSame($errors, $exception->getErrors());
        $this->assertSame('The request body failed validation.', $exception->getMessage());
    }

    public function testCustomMessageIsKept(): void
    {
        $exception = new ValidationException([], 'custom message');
        $this->assertSame('custom message', $exception->getMessage());
    }
}
