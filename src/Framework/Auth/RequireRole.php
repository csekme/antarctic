<?php

declare(strict_types=1);

namespace Framework\Auth;

use Attribute;

/**
 * Egy vagy több szerep megkövetelése. `#[RequireAuth]`-ot implikál (a
 * Dispatcher először az autentikáltságot ellenőrzi, csak utána a roleot).
 *
 *     #[RequireRole('admin')]
 *     #[RequireRole('admin', 'editor')]  // bármelyik elég
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequireRole
{
    /** @var list<string> */
    public readonly array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = array_values($roles);
    }
}
