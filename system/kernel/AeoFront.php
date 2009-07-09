<?php

    # front controller
    class AeoFront
    {
        private $request = null;

        private $result = array();

        function __construct()
        {
            $this->result['module'] = 'index';
            $this->result['controller'] = 'index';
            $this->result['argv'] = array();
        }

        public function run($url)
        {
            $this->request = $url;

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
                        $this->result['controller'] = 'index';
                    } else{
                        // this controller in 'index' module
                        $this->result['controller'] = $seg[0];
                    }

                    break;
                case 2:
                    if (in_array($seg[0], $module)) {
                        $this->result['module'] = $seg[0];
                        $this->result['controller'] = $seg[1];
                    } else {
                        // this controller in 'index' group with argv
                        $this->result['controller'] = $seg[0];
                        $this->result['argv'][] = $seg[1];
                    }

                    break;
                default:
                    if (in_array($seg[0], $module)) {
                        $this->result['module'] = $seg[0];
                        $this->result['controller'] = $seg[1];
                        $this->result['argv'] = array_slice($seg, 2);
                    } else {
                        $this->result['controller'] = $seg[0];
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

            $path = A_PREFIX . "module/$module/controller/{$module}_{$controller}.php";

            /* Setup environment variable */
            global $env;
            $env['module'] = $module;
            $env['controller'] = $controller;

            if (is_file($path)) {
                /* Load controller */
                $controller = Aeolus::loadController($controller, $module);

                $controller($argv);
            } else {
                // looking for wildcard handler
                $controller = Aeolus::loadController('wildcard', $module);

                $controller($this->request);
            }
        }
    }
?>
