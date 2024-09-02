<?php

namespace Framework;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
class Path
{
    public function __construct(public ?string $path = "", public ?string $method = AbstractController::GET)
    {
    }

}