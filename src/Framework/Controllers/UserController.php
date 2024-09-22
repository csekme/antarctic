<?php

namespace Framework\Controllers;

use Framework\AbstractController;
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
    #[Path(method: AbstractController::GET)]
    public function indexAction(): Response
    {
        $uuid = $this->request->get['uuid'] ?? null;
        $users = User::findAll();
        $user = null;
        $sowModal = false;
        if ($uuid) {
            foreach ($users as $u) {
                if ($u->uuid === $uuid) {
                    $user = $u;
                    $showModal = true;
                  break;
                }
            }
        }
        return $this->view('User/users.twig', [ "users" => $users, "user" => $user ]);
    }

    #[Path(method: AbstractController::POST)]
    public function saveAction(): Response
    {
        $user = new User($this->request->post);
        if ($user->save()) {
            $this->redirect('/user');
        } else {
            $users = User::findAll();
            return $this->view('User/users.twig',[ 'errors' => $user->getErrors(), 'user' => $user, "users" => $users, View::$SHOW_MODAL_BY_ID => 'userModal' ]);
        }
    }


}
