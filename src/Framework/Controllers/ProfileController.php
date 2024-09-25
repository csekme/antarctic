<?php

namespace Framework\Controllers;
use Framework\AbstractController;
use Framework\Controller;
use Framework\Models\TwoFactorModel;
use Framework\Path;
use Framework\RequireLogin;
use Framework\Response;
use Framework\Models\User as User;
use Framework\Auth as Auth;


#[RequireLogin]
#[Path('/profile')]
class ProfileController extends Controller {

    #[Path(method: AbstractController::GET)]
    public function showPage() : Response {
        $user = User::findByUUID(Auth::getUser()->uuid);
        $twoFactorApp = null;
        $twoFactorEmail = null;
        $twoFactorMethods = TwoFactorModel::findByUserId($user->id);
        foreach ($twoFactorMethods as $method) {
            if ($method->method == TwoFactorModel::METHOD_APP) {
                $twoFactorApp = $method;
            } else if ($method->method == TwoFactorModel::METHOD_EMAIL) {
                $twoFactorEmail = $method;
            }
        }
        $twoFactor = [
            "app" => $twoFactorApp,
            "email" => $twoFactorEmail
        ];
        return $this->view('User/profile.twig', ["user"=>$user, "twoFactor" => $twoFactor]);
    }

    #[Path(path: '/updateProfile', method: AbstractController::POST)]
    public function updateProfile() : Response {
        $payload = $this->request->json;
        $user =new User($payload);
        if ($user->updateProfile()) {
            return Response::json(["success" => true]);
        } else {
            return Response::json(["success" => false, "errors" => $user->getErrors()], 400);
        }
    }


}