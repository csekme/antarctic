<?php

declare(strict_types=1);

namespace Framework\Auth;

enum ConfirmStatus
{
    case Enabled;
    case NotStarted;
    case AlreadyEnabled;
    case InvalidCode;
}
