<?php

declare(strict_types=1);

namespace Framework\Auth;

use Attribute;

/**
 * Egy kontroller method-ra téve kötelez érvényes Bearer JWT-t a kéréshez.
 * Ha hiányzik vagy érvénytelen, a Dispatcher 401-et dob.
 *
 *     #[Path(path: '/api/v1/me', method: 'GET')]
 *     #[RequireAuth]
 *     public function me(): Response { … }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class RequireAuth
{
}
