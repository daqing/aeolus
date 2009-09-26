<?php

/* Aeolus front controller */
class AeoFront
{
    # system object
    private $sys;
    private $url;

    function __construct($system)
    {
        $this->sys = $system;
    }

    public function route($url)
    {
        $this->url = $url;

        # Remove base url from request
        $request = trim(substr(strtolower($url), strlen($this->sys->indexURL)), '/\\');

        $segments = (strlen($request)) ? explode('/', $request) : '/';
        if ($segments == '/') {
            // load default controller
            $controller = $this->newController('index');

            if ($controller == null) {
                // no controller found
                $this->sys->quit('no_controller_found');
            }

            $this->runController($controller, 'index');
        } else {
            if (isset($segments[0]) && in_array($segments[0], $this->sys->validControllers)) {
                $controller = $this->newController($segments[0]);
                if ($controller == null) {
                    $this->sys->quit('no_controller_found');
                }

                $action = 'index';
                $argv = array();
                if (isset($segments[1])) {
                    $action = $segments[1];
                }

                if (isset($segments[2])) {
                    $argv = array_slice($segments, 1);
                }

                $this->runController($controller, $action, $argv);
            } else {
                $this->sys->quit('cannot_route_url');
            }
        }
    }

    private function runController($controller, $action='index', $argv=array())
    {
        if (! is_object($controller)) {
            return;
        }

        if (method_exists($controller, $action)) {
            $controller->index($this->sys, $argv);
        } else if (method_exists($controller, 'fallback')) {
            $controller->fallback($this->sys, $this->url);
        } else {
            $this->sys->quit('cannot_route_url');
        }
    }

    # create a controller instance
    private function newController($name)
    {
        $className = ucfirst($name) . 'Controller';
        $path = $this->sys->homePath . "module/controller/{$className}.php";
        if (! is_file($path)) {
            return null;
        }

        require $path;
        if (! class_exists($className, false)) {
            return null;
        }

        return new $className();
    }
}

?>
