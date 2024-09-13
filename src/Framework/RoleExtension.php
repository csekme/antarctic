<?php

namespace Framework;

class RoleExtension extends \Twig\Extension\AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('hasRole', [$this, 'hasRole']),
            new \Twig\TwigFunction('isAdmin', [$this, 'isAdmin']),
            new \Twig\TwigFunction('isNotAdmin', [$this, 'isNotAdmin']),
            new \Twig\TwigFunction('isLogged', [$this, 'isLogged']),
        ];
    }


    public function isLogged(): bool
    {
        return Auth::getUser() != null;
    }

    /**
     * @throws \Exception
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('ROLE_ADMIN');
    }

    public function isNotAdmin()
    {
        return !$this->isAdmin();
    }

    /**
     * @throws \Exception
     */
    public function hasRole($role): bool
    {
        $user = Auth::getUser();
        $userRoles = $user->getRoles();

        if (is_string($role)) {
            $role = [$role];
        }

        foreach ($role as $r) {
            if (in_array($r, $userRoles)) {
                return true;
            }
        }
        return false;
    }
}
