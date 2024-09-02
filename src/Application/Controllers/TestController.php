<?php

namespace Application\Controllers;

use Framework\Controller as Controller;
use Framework\Path as Path;

#[Path('/test')]
class TestController extends Controller
{
    #[Path(path:'/test', method:Controller::GET)]
    function testAction() : void {
        
        print "Hello, World!";
    }


}