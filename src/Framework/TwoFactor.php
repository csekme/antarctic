<?php

namespace Framework;
use AllowDynamicProperties;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
/**
 * @AllowDynamicProperties
 * @property TwoFactorAuth $tfa
 */
#[\AllowDynamicProperties]
class TwoFactor
{


    /**
     * @throws TwoFactorAuthException
     */
    public function __construct()
    {
        $this->tfa = new TwoFactorAuth(new BaconQrCodeProvider());
    }

    /**
     * @return TwoFactorAuth
     */
    public function generateSecretKey(): String
    {
        return $this->tfa->createSecret();
    }


    public function getQRCodeImageAsDataUri(String $secret): String
    {
        return $this->tfa->getQRCodeImageAsDataUri('hello', $secret);
    }


}