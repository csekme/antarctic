<?php
namespace Framework\Routing;

interface Router {
 
    /**
     * Add a route to the routing table
     *
     * @param string $route  The route URL
     * @param array  $params Parameters (controller, action, etc.)
     *
     * @return void
     */
    function add($route, $params = []) : void;

    function match(string $url): array | bool;
}