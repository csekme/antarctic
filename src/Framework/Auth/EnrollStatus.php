<?php

declare(strict_types=1);

namespace Framework\Auth;

enum EnrollStatus
{
    case Started;
    case AlreadyEnabled;
    case Failed;
}
