<?php

namespace Framework;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
class HasRoles
{
    public function __construct(public $roles = [])
    {
    }

}