<?php

declare(strict_types=1);

namespace Framework\Auth;

enum RegistrationStatus
{
    case Created;
    case EmailTaken;
    case UsernameTaken;
    case Failed;
}
