<?php

namespace Framework\Controllers;
use Framework\AbstractController;
use Framework\Controller;
use Framework\Flash;
use Framework\Path;
use Framework\RequireLogin;
use Framework\Response;
use Framework\Models\User as User;
use Framework\Auth as Auth;
use Framework\View;

#[RequireLogin]
#[Path('/profile')]
class ProfileController extends Controller {

    #[Path(method: AbstractController::GET)]
    public function showPage() : Response {
        $user = User::findByUUID(Auth::getUser()->uuid);
        return $this->view('User/profile.twig', ["user"=>$user]);
    }

    #[Path(method: AbstractController::POST)]
    public function savePage($data) : Response {
        $user = new User($data);
        if ($user->update()) {
            Flash::addMessage('User data has been successfully updated.', type: Flash::SUCCESS);
            $this->redirect('/profile');
        }
        return $this->view('User/profile.twig', [ "user"=>$user , "errors" => $user->getErrors(), View::$SHOW_MODAL_BY_ID => 'userModal' ]);
    }

}