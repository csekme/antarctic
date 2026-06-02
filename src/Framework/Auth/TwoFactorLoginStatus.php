<?php

declare(strict_types=1);

namespace Framework\Auth;

enum TwoFactorLoginStatus
{
    case Ok;
    case ChallengeInvalid;
    case UserInactive;
    case NotEnabled;
    case InvalidCode;
}
