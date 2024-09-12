<?php

namespace Framework;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
class RequireLogin
{
    public function __construct()
    {
    }

}