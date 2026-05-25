<?php

declare(strict_types=1);

namespace Framework\Validation;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see RequestHydrator} when a DTO fails hydration or symfony
 * validation. The HTTP code is fixed at 422 so the
 * {@see \Framework\Http\ErrorHandlerMiddleware} maps it to a RFC 7807
 * `application/problem+json` body with the field-level `errors` map.
 *
 * @phpstan-type ErrorMap array<string, list<string>>
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param ErrorMap $errors property-path → list of messages
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The request body failed validation.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }

    /**
     * @return ErrorMap
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
