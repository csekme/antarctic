<?php

namespace Framework\Controllers;

use Framework\Controller;
use Framework\HasRoles;
use Framework\Models\User;
use Framework\Path;
use Framework\RequireLogin;
use Framework\Response;
use Framework\View;

#[RequireLogin]
#[HasRoles(['ROLE_ADMIN'])]
#[Path('/user')]
class UserController extends Controller
{
    public function indexAction(): Response
    {
        $users = User::findAll();
        return $this->view('User/users.twig', [ "users" => $users ]);
    }


}
