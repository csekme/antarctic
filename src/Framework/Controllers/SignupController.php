<?php

namespace Framework\Controllers;

use Framework\AbstractController;
use Framework\Controller;
use Framework\Flash;
use Framework\Models\User;
use Framework\Path;
use Framework\Response;
use Random\RandomException;

#[Path('/signup')]
class SignupController extends Controller {

    #[Path(path: '/new', method: AbstractController::GET)]
    function newAction() : Response {
        $step = $this->request->get['step'] ?? 'signup';
        return $this->view('User/signup.twig',["step" =>  $step]);
    }

    /**
     * @throws RandomException
     * @throws \Exception
     */
    #[Path(path: '/signup', method: AbstractController::POST)]
    function signupAction() : Response {
        $user = new User($this->request->post);
        if ($user->save()) {
            $this->redirect('/signup/new?step=activate');
        } else {
            return $this->view('User/signup.twig',[ 'step'=>'signup', 'errors' => $user->getErrors(), 'user' => $user ]);
        }

    }

}