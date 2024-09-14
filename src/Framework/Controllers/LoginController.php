<?php

namespace Framework\Controllers;

use Framework\Auth;
use Framework\AbstractController;
use Framework\Controller;
use Framework\Flash;
use Framework\Models\User;
use Framework\Path;
use Framework\Response;

#[Path('/login')]
class LoginController extends Controller {

    #[Path(method: AbstractController::GET)]
    public function login() : Response {
        return $this->view('Login/login.twig', []);
    }

    /**
     * @throws \Exception
     */
    #[Path(method: AbstractController::POST)]
    public function enter() : Response {
        $user = User::authenticate($_POST['email'], $_POST['password']);
        $remember_me = isset($_POST['remember_me']);
        if ($user) {
            Auth::login($user, $remember_me);
            $this->redirect(Auth::getReturnToPage());
        } else {

            Flash::addMessage(message: 'The login was unsuccessful. Please try again.',title: 'Attention',type: Flash::WARNING);
            $this->redirect('/');
        }
    }


}
