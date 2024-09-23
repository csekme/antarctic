<?php
namespace Framework\Controllers;
use Framework\AbstractController;
use Framework\Auth;
use Framework\Dal;
use Framework\Flash;
use Framework\Models\User;
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
        $user = User::findByID(Auth::getUser()->id);
        if ($user->two_factor_secret_key != null) {
            Flash::addMessage('Two factor is already set', 'Two factor', type:  Flash::WARNING);
            $this->redirect('/profile');
        }
        $twoFactor = new TwoFactor();
        $secret = $twoFactor->generateSecretKey();
        $qrCode = $twoFactor->getQRCodeImageAsDataUri($secret);
        return $this->view('TwoFactor/two-factor.twig', [ 'qrCode' => $qrCode, 'secret' => $secret ]);
    }

    #[Path(path: "/set", method: AbstractController::POST)]
    public function set() : Response {
        $user = User::findByID(Auth::getUser()->id);
        $user->two_factor = $this->request->json['twoFactor']?Dal::TRUE:Dal::FALSE;
        $user->updateTwoFactorFields();
        return Response::json(['success' => true]);
    }

    /**
     * @throws \Exception
     */
    #[Path(method: AbstractController::POST)]
    public function validate() : Response
    {
        $twoFactor = new TwoFactor();
        $passCode = $this->request->post['field-1'] . $this->request->post['field-2'] . $this->request->post['field-3'] . $this->request->post['field-4'] . $this->request->post['field-5'] . $this->request->post['field-6'];
        $user = User::findByID(Auth::getUser()->id);
        $secret = $this->request->post['secret'];
        $qrCode = $twoFactor->getQRCodeImageAsDataUri($secret);
        if ($twoFactor->verifyCode($secret, $passCode)) {
            $user->two_factor = Dal::TRUE;
            $user->two_factor_secret_key = $secret;
            $user->updateTwoFactorFields();
            Flash::addMessage('Success validation', 'Two factor validation', type:  Flash::INFO);
            $this->redirect('/profile');
        } else {
            Flash::addMessage('Invalid code', 'Two factor validation', type:  Flash::WARNING);
        }

        return $this->view('TwoFactor/two-factor.twig', [ 'qrCode' => $qrCode, 'secret' => $secret ]);
    }
}
