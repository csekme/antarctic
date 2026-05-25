<?php

declare(strict_types=1);

namespace Framework\Validation;

/**
 * Marker interface for request DTOs. Any controller action parameter whose
 * type implements {@see Validatable} is auto-hydrated from
 * {@see \Framework\Request::getJson()} and validated by {@see RequestHydrator}
 * before the action is invoked. Validation failures bubble up as
 * {@see ValidationException} → 422 problem+json with an `errors` field.
 */
interface Validatable
{
}
