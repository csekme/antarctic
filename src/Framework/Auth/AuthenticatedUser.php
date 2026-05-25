<?php

declare(strict_types=1);

namespace Framework\Auth;

/**
 * Egy verifikált access tokenből kibontott user-azonosság. Stateless,
 * immutable — a request attribútumon közlekedik. Nem azonos a
 * `Framework\Models\User` modellel; arra DB lookup kell, ha kelleni fog.
 */
final class AuthenticatedUser
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly int $id,
        public readonly array $roles = [],
        public readonly ?string $jti = null,
    ) {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @param list<string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }
}
