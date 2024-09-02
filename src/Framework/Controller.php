<?php
namespace Framework;
use Framework\AbstractController as AbstractController;

abstract class Controller extends AbstractController {
/**
     * Parameters from the matched route
     * @var array
     */
    protected $route_params = [];

    /**
     * Class constructor
     *
     * @param array $route_params  Parameters from the route
     *
     * @return void
     */
    public function __construct($route_params)
    {
        $this->route_params = $route_params;
    }

    /**
     * Magic method called when a non-existent or inaccessible method is
     * called on an object of this class. Used to execute before and after
     * filter methods on action methods. Action methods need to be named
     * with an "Action" suffix, e.g. indexAction, showAction etc.
     *
     * @param string $name  Method name
     * @param array $args Arguments passed to the method
     *
     * @return void
     */
    public function __call($name, $args) : Response
    {
        $method = $name . 'Action';

        if (method_exists($this, $method)) {
            if ($this->before() !== false) {
                call_user_func_array([$this, $method], $args);
                $this->after();
            }
        } else {
            throw new \Exception("Method $method not found in controller " . get_class($this));
        }
        return $this->response;
    }

    /**
     * Before filter - called before an action method.
     *
     * @return bool 
     */
    protected function before() : bool
    {
        return true;
    }

    /**
     * After filter - called after an action method.
     *
     * @return void
     */
    protected function after()
    {
    }


    /**
     * Require the user to be logged in before giving access to the requested page.
     * Remember the requested page for later, then redirect to the login page
     * 
     * @return void
     */
    public function requireLogin()
    {
        //if (! Auth::getUser() )
        //{

       //     Flash::addMessage('Kérem előbb jelentkezzen be!','Figyelem!', null);

       //     Auth::rememberRequestedPage();
            
      //      $this->redirect('/login');
      //  }
    }

    /**
     * Require the user to be an administrator efore giving access to the requested page.
     * @return void
     */
    public function requireAdmin()
    {
      //  if (! Auth::isAdmin() ){

     //       Flash::addMessage('Ehhez a kéréshez adminisztrátori jogosultság szükséges!', 'Figyelem!', Flash::WARNING );
            
      //      $this->redirect('/login');
     //   }
    }

}