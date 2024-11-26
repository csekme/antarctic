<?php
namespace Application\Controllers;
use Framework\AbstractController;
use Framework\Controller as Controller;
use Framework\Path as Path;
use Framework\Response;
#[Path("/")]
class TestController extends Controller
{
    #[Path(method: AbstractController::GET)]
    function testAction() : Response {
        $response = new Response();
        $response->setBody("Hello, World!");
        return $response;
    }


}