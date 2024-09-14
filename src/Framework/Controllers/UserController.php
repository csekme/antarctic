<?php

namespace Framework\Controllers;

use Framework\AbstractController;
use Framework\Controller;
use Framework\HasRoles;
use Framework\Models\User;
use Framework\Path;
use Framework\RequireLogin;
use Framework\Response;

#[RequireLogin]
#[HasRoles(['ROLE_ADMIN'])]
#[Path('/user')]
class UserController extends Controller
{
    #[Path('/getAll', method: AbstractController::GET)]
    public function indexAction(): Response
    {
        $users = User::findAll();
        return $this->view('User/users.twig', [ "users" => $users ]);
    }

    #[Path('/save', method: AbstractController::POST)]
    public function saveAction(): Response
    {
        $user = new User($this->request->post);
        if ($user->save()) {
            $this->redirect('/user');
        }
    }


}
