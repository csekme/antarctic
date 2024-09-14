<?php

namespace Framework\Controllers;

use Framework\AbstractController;
use Framework\Auth;
use Framework\Controller;
use Framework\Path;
#[Path('/logout')]
class  LogoutController extends Controller
{
    /**
     * Logout the user
     * @throws \Exception
     */
    #[Path(method: AbstractController::GET)]
    function logoutAction() : void
    {
        Auth::logout();
        $this->redirect('/');
    }

}