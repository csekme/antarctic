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
        $email = 'some@email.com';
        return $this->view('User/signup.twig',[ 'email' => $email ]);
    }

    /**
     * @throws RandomException
     */
    #[Path(path: '/signup', method: AbstractController::POST)]
    function signupAction() : Response {
        $user = new User($this->request->post);
        Flash::addMessage(message: '<b>'.$user->username.'</b> has been successfully created.', title: 'New User', type: Flash::SUCCESS);
        $this->redirect('');
    }

}