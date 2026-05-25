<?php

namespace Framework;
/**
 * Class RoleExtension
 * @package Framework
 * @since 1.0
 * @license GPL-3.0-or-later
 * @author Krisztián Csekme
 * @category Framework
 * @version 1.0
 * @Path(path: '/Framework/RoleExtension.php')
 */
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
        return false;
    }

    public function isAdmin(): bool
    {
        return false;
    }

    public function isNotAdmin(): bool
    {
        return true;
    }

    public function hasRole($role): bool
    {
        return false;
    }
}
