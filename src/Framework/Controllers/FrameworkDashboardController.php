<?php

namespace Framework\Controllers;

use Framework\Controller as Controller;
use Framework\Path as Path;
use Framework\Config as Config;
use Framework\Response;
use Framework\Flash as Flash;

#[Path('/FrameworkDashboard')]
class FrameworkDashboardController extends Controller
{

    
    #[Path(path:'', method:Controller::GET)]
    function indexAction() : Response {
        return $this->view('dashboard.twig',
        [
            'is_exist_appconfig' => file_exists(dirname(__DIR__) . Config::APPLICATION_CONFIG_FILE)?"true":"false",
            'year' => date("Y")
        ]);
    }
}