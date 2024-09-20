<?php
namespace Framework\Controllers;
use Framework\AbstractController;
use Framework\Path;
use Framework\RequireLogin;
use Framework\Response;
use Framework\Controller;
use Framework\TwoFactor;
#[Path('/two-factor')]
#[RequireLogin]
class TwoFactorController extends Controller
{
    #[Path(method: AbstractController::GET)]
    public function get() : Response
    {
        $twoFactor = new TwoFactor();
        $secret = $twoFactor->generateSecretKey();
        $qrCode = $twoFactor->getQRCodeImageAsDataUri($secret);
        return $this->view('TwoFactor/two-factor.twig', [ 'qrCode' => $qrCode ]);
    }
}
