<?php

    /*
     * Front controller
     */

    class AeoFront
    {
        private $request;

        private $result;

        function __construct()
        {
          $this->request = $_SERVER['REQUEST_URI'];

          $this->result['module'] = 'index';
          $this->result['controller'] = 'index_index';
          $this->result['argv'] = array();
        }

        public function run()
        {
            $this->process();

            $this->launch();
        }

        /* Process the HTTP request */
        private function process()
        {
            /* Remove base url from request */
            $request = trim(substr(strtolower($this->request), strlen(BASE_URL)), '/\\');

            /* Get segments */
            $seg = (strlen($request)) ? explode('/', $request) : '/';

            if ('/' !== $seg) {
                require A_PREFIX . 'config/system/module.php';

                $size = count($seg);
                switch ($size) {
                case 1:
                    if (in_array($seg[0], $module)) {
                        // default controller in this module
                        $this->result['module'] = $seg[0];
                        $this->result['controller'] = $seg[0] . '_index';
                    } else{
                        // this controller in 'index' module
                        $this->result['controller'] = 'index_' . $seg[0];
                    }

                    break;
                case 2:
                    if (in_array($seg[0], $module)) {
                        $this->result['module'] = $seg[0];
                        $this->result['controller'] = $seg[0] . '_' .$seg[1];
                    } else {
                        // this controller in 'index' group with argv
                        $this->result['controller'] = 'index_' . $seg[0];
                        $this->result['argv'][] = $seg[1];
                    }

                    break;
                default:
                    if (in_array($seg[0], $module)) {
                        $this->result['module'] = $seg[0];
                        $this->result['controller'] = $seg[0] . '_' . $seg[1];
                        $this->result['argv'] = array_slice($seg, 2);
                    } else {
                        $this->result['controller'] = 'index_' . $seg[0];
                        $this->result['argv'] = array_slice($seg, 1);
                    }

                    break;
                }
            }
        }

        /* Launch application controllers */
        private function launch()
        {
            extract($this->result);

            $path = A_PREFIX . "module/$module/controller/$controller.php";

            /* Setup environment variable */
            global $thisModule;
            $thisModule = $module;

            if (is_file($path)) {
                /* Load controller */
                require $path;

                if (function_exists($controller)) {
                    $controller($this->result['argv']);
                } else {
                    throw new AeoException('controller_not_defined', array('module' => $module, 'controller' => $controller));
                }
            } else {
                // looking for wildcard handler
                $wildcard = $module . '_wildcard';
                $wild_path = A_PREFIX . "module/$module/controller/$wildcard.php";
                if (is_file($wild_path)) {
                    require $wild_path;

                    if (function_exists($wildcard)) {
                        $wildcard($this->request);
                    } else {
                        throw new AeoException('wildcard_not_defined', array('module' => $module, 'controller' => $controller, 'url' => $this->request));
                    }
                } else {
                    throw new AeoException('route_failed', array('module' => $module, 'controller' => $controller, 'url' => $this->request));
                }
            }
        }
    }
?>
