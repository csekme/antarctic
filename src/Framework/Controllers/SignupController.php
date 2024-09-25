<?php

namespace Framework\Controllers;

use Framework\AbstractController;
use Framework\Config;
use Framework\Controller;
use Framework\Flash;
use Framework\Mail;
use Framework\Models\User;
use Framework\Path;
use Framework\Response;
use Framework\Token;
use Framework\View;
use Random\RandomException;

#[Path('/signup')]
class SignupController extends Controller {

    #[Path(method: AbstractController::GET)]
    function new() : Response {
        $step = $this->request->get['step'] ?? 'signup';
        return $this->view('Signup/signup.twig',["step" =>  $step]);
    }

    /**
     * @throws RandomException
     * @throws \Exception
     */
    #[Path(method: AbstractController::POST)]
    function signup() : Response {
        $user = new User($this->request->post);
        if ($user->save()) {
            $url = Config::get_server_protocol().'://'. $_SERVER['HTTP_HOST'] . '/signup/activate/' . $user->activation_token;
            $html = View::getTemplate('Signup/activation_email.html', ['url' => $url]);
            $text = View::getTemplate('Signup/activation_email.txt', ['url' => $url]);
            Mail::send($user->email, 'Registration confirmation', $text, $html);
            $this->redirect('/signup?step=activate');
        } else {
            return $this->view('Signup/signup.twig',[ 'step'=>'signup', 'errors' => $user->getErrorsAsJson(), 'user' => $user ]);
        }
        $this->redirect('/signup');
    }

    /**
     * @throws \Exception
     */
    #[Path(path: '/activate/{token:[\da-f]+}', method: AbstractController::GET)]
    function activate() : Response {
        $token = new Token($this->route_params['token']);
        $hashed_token = $token->getHash();
        if (User::activateByActivationHash($hashed_token)){
            $this->redirect('/signup/success');
        }
        $this->redirect('/signup/unsuccessful');

    }

    #[Path(path: '/success', method: AbstractController::GET)]
    function successAction() : Response
    {
        return $this->view('Signup/signup.twig', ["step"=>'success']);
    }

    #[Path(path: '/unsuccessful', method: AbstractController::GET)]
    function unsuccessfulAction() : Response
    {
        $server_administrator_email = null;
        if (isset(Config::get_config()["administrator"]["email"])) {
            $server_administrator_email = Config::get_config()["administrator"]["email"];
        }
        return $this->view('Signup/signup.twig', ["step"=>'unsuccessful', 'server_administrator_email'=>$server_administrator_email ]);
    }


}