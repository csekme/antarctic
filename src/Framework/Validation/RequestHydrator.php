<?php

declare(strict_types=1);

namespace Framework\Validation;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Validation;
use Throwable;

/**
 * Hydrates a {@see Validatable} DTO from a decoded JSON payload and runs the
 * symfony validator on the resulting object. Hydration is constructor-driven:
 * the DTO declares its fields as constructor-promoted properties (typically
 * `readonly`), and the hydrator matches payload keys to parameter names. Type
 * coercion is intentionally avoided — JSON types must match the declared
 * parameter types, otherwise a `type` error is added to the validation report.
 *
 * Missing keys fall back to the parameter's default value. If the constructor
 * does not provide a default, the value is reported as `missing`.
 */
final class RequestHydrator
{
    private ValidatorInterface $validator;

    public function __construct(?ValidatorInterface $validator = null)
    {
        $this->validator = $validator ?? Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * @template T of Validatable
     * @param class-string<T> $class
     * @param array<string, mixed> $payload
     * @return T
     * @throws ValidationException
     */
    public function hydrate(string $class, array $payload): Validatable
    {
        if (!is_subclass_of($class, Validatable::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Hydrator target "%s" must implement %s.',
                $class,
                Validatable::class,
            ));
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        /** @var array<string, list<string>> $errors */
        $errors = [];
        $args = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (array_key_exists($name, $payload)) {
                    $value = $payload[$name];
                    if (!$this->valueMatchesType($value, $parameter)) {
                        $errors[$name][] = $this->typeMismatchMessage($parameter);
                        continue;
                    }
                    $args[$name] = $value;
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[$name] = $parameter->getDefaultValue();
                } elseif ($parameter->allowsNull()) {
                    $args[$name] = null;
                } else {
                    $errors[$name][] = 'This field is required.';
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        try {
            /** @var T $instance */
            $instance = $reflection->newInstanceArgs($args);
        } catch (Throwable $e) {
            // A defensive net for residual coercion problems (e.g. enum
            // backings, readonly invariants). The type-check above catches
            // the common cases, so reaching this branch implies a genuine
            // construction failure rather than a malformed payload field.
            throw new ValidationException(
                ['_' => [$e->getMessage()]],
                'The request body failed validation.',
                $e,
            );
        }

        $violations = $this->validator->validate($instance);
        if (count($violations) > 0) {
            /** @var array<string, list<string>> $report */
            $report = [];
            foreach ($violations as $violation) {
                $path = $violation->getPropertyPath();
                if ($path === '') {
                    $path = '_';
                }
                $report[$path][] = (string) $violation->getMessage();
            }
            throw new ValidationException($report);
        }

        return $instance;
    }

    private function valueMatchesType(mixed $value, ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            return true;
        }
        if ($value === null) {
            return $type->allowsNull();
        }

        $name = $type->getName();
        return match ($name) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'mixed' => true,
            default => $value instanceof $name,
        };
    }

    private function typeMismatchMessage(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : 'unknown';
        return sprintf('This field must be of type %s.', $name);
    }
}
