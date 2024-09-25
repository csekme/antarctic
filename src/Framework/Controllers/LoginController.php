<?php

namespace Framework\Controllers;

use Framework\Auth;
use Framework\AbstractController;
use Framework\Controller;
use Framework\Dal;
use Framework\Flash;
use Framework\Models\TwoFactorModel;
use Framework\Models\User;
use Framework\Path;
use Framework\Response;
use Framework\TwoFactor;

#[Path(path: '/login')]
class LoginController extends Controller {

    /**
     * Login request
     * @return Response
     */
    #[Path(method: AbstractController::GET)]
    public function login() : Response {
        if (isset($_SESSION['twoFactorUser'])) {
            $user = User::findByUuid($_SESSION['twoFactorUser']);
            $twoFactorModelArr = TwoFactorModel::findByUserId($user->id);
            $twoFactorArr = [];
            foreach ($twoFactorModelArr as $twoFactorModel) {
                if ($twoFactorModel->enabled == Dal::TRUE) {
                    $twoFactorArr[] = $twoFactorModel->method;
                }
            }
            return $this->view('Login/login.twig', ['twoFactor' => true, 'twoFactorArr' => $twoFactorArr]);
        }
        return $this->view('Login/login.twig', []);
    }

    /**
     * @throws \Exception
     */
    #[Path(method: AbstractController::POST)]
    public function enter() : Response {
        $user = User::authenticate($_POST['email'], $_POST['password']);
        if (!$user) {
            Flash::addMessage(message: 'The login was unsuccessful. Please try again.', title: 'Attention', type: Flash::WARNING);
            $this->redirect('/');
        }
        $twoFactorModelArr = TwoFactorModel::findByUserId($user->id);
        $twoFactorArr = [];
        if ($twoFactorModelArr != null) {
            foreach ($twoFactorModelArr as $twoFactorModel) {
                if ($twoFactorModel->enabled == Dal::TRUE) {
                    $twoFactorArr[] = $twoFactorModel->method;
                }
            }
        }
        if (sizeof($twoFactorArr) > 0) {
            $_SESSION['twoFactorUser'] = $user->uuid;
            $this->redirect('/login');
        } else {
            $remember_me = isset($_POST['remember_me']);
            Auth::login($user, $remember_me);
            $this->redirect(Auth::getReturnToPage());
        }
    }

    #[Path(path: "/two-factor",method: AbstractController::POST)]
    public function enterTwoFactor() : Response {
        if (isset($_SESSION['twoFactorUser'])) {
            $twoFactor = new TwoFactor();
            $passCode = $this->request->post['field-1'] . $this->request->post['field-2'] . $this->request->post['field-3'] . $this->request->post['field-4'] . $this->request->post['field-5'] . $this->request->post['field-6'];
            $user = User::findByUuid($_SESSION['twoFactorUser']);
            $twoFactorModel = TwoFactorModel::findByUserIdAndMethod($user->id, TwoFactorModel::METHOD_APP);
            if ($twoFactorModel != null) {
                $secret = $twoFactorModel->secret_key;
                if ($twoFactor->verifyCode($secret, $passCode)) {
                    unset($_SESSION['twoFactorUser']);
                    Auth::login($user, false);
                    $this->redirect(Auth::getReturnToPage());
                } else {
                    Flash::addMessage(message: 'The login was unsuccessful. Please try again.', title: 'Attention', type: Flash::WARNING);
                    unset($_SESSION['twoFactorUser']);
                    $this->redirect('/');
                }

            } else {
                Flash::addMessage(message: 'The login was unsuccessful. Please try again.', title: 'Attention', type: Flash::WARNING);
                unset($_SESSION['twoFactorUser']);
                $this->redirect('/');
            }

        } else {
            $this->redirect('/');
        }
    }


}
