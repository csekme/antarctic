<?php

namespace Framework\Controllers;

use Framework\AbstractController;
use Framework\Controller as Controller;
use Framework\HasRoles;
use Framework\Models\Role;
use Framework\Path as Path;
use Framework\Config as Config;
use Framework\RequireLogin;
use Framework\Response;
use Framework\Flash as Flash;
use Framework\Mail as Mail;

#[Path('/')]
#[RequireLogin]
class FrameworkDashboardController extends Controller
{
    
    #[Path(method: AbstractController::GET)]
    function indexAction() : Response {
        return $this->view('dashboard.twig',
        [
            'is_exist_appconfig' => file_exists(dirname($_SERVER['DOCUMENT_ROOT']). Config::APPLICATION_CONFIG_FILE)?"true":"false",
            'year' => date("Y")
        ]);
    }


    #[Path(path: '/test-email', method: AbstractController::POST)]
    function testEmailAction() : Response {
        $res = Mail::sendHtmlMessage($_POST["email"], $_POST["subject"], $_POST["body"]);
        if ($res === true) {
            Flash::addMessage(message: "Üzenet sikeresen elküldve", type: Flash::SUCCESS);
        } else {
            Flash::addMessage(message: "<h5>An error has occurred</h5>". $res, type: Flash::ERROR);
        }
        $this->redirect("");
        
    }
}