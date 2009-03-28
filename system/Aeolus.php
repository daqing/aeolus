<?php

    /* Factory class */

    class Aeolus
    {
        private static $loaded = array();

        /**
         * Load a class
         *
         * This method loads a class and makes sure to load it only once
         *
         * @param string $path path to file to be included
         * @return void
         */
        public function loadClass($className)
        {
            $path = $className . '.php';
            if (!in_array($path, self::$loaded)) {
                require $path;

                self::$loaded[] = $path;
            }
        }

        /**
         * Load module controller
         *
         * @param string $controller controller name
         * @param string $module module name
         * @returns string $action action to be called directly
         */
        public function loadController($controller, $module='this')
        {
            if ('this' == $module) {
                global $thisModule;

                $module = $thisModule;
            }

            $action = $module . '_' . $controller;

            $path = A_PREFIX . "module/$module/controller/$action.php";

            if (APP_DEBUG)
                clearstatcache();

            if (is_file($path)) {
                require $path;

                if (function_exists($action)) {
                    return $action;
                } else {
                    throw new AeoException(
                        array(
                            'name' => 'ControllerNotDefined',
                            'detail' => 'controller not defined',
                            'runtime' => array(
                                'module' => $module,
                                'type' => 'controller',
                                'controller' => $controller
                            )
                        )
                    );
                }
            } else {
                throw new AeoException(
                    array('name' => 'ControllerNotFound',
                        'detail' => 'controller not found',
                        'runtime' => array(
                            'module' => $module,
                            'type' => 'controller',
                            'controller' => $controller
                        )
                    )
                );
            }
        }

        /* Get an instance of a model class */
        public function newModel($model, $module='this')
        {
            if ('this' == $module) {
                global $thisModule;

                $module = $thisModule;
            }

            if (APP_DEBUG)
                clearstatcache();

            $obj = null;

            $modelClass = ucfirst($module) . $model . 'Model';
            $path = A_PREFIX . "module/$module/model/" . $modelClass;
            if (is_file($path . '.php')) {
                self::loadClass('kernel/AeoModel');
                self::loadClass($path);

                if (class_exists($modelClass))
                    $obj = new $modelClass();
            }

            return $obj;
        }

        /**
         * Get an instance of a view class
         *
         */
        public function newView($view, $module='this')
        {
            if ('this' == $module) {
                global $thisModule;

                $module = $thisModule;
            }

            if (APP_DEBUG)
                clearstatcache();

            $obj = null;
            $viewClass = ucfirst($module) . $view . 'View';
            $path = A_PREFIX . "module/$module/view/$viewClass";
            if (is_file($path . '.php')) {
                self::loadClass('kernel/AeoView');
                self::loadClass($path);

                if (class_exists($viewClass))
                    $obj = new $viewClass();
                else
                    throw new AeoException(
                        array(
                            'name' => 'ViewNotDefined',
                            'detail' => 'view class not defined',
                            'runtime' => array(
                                'module' => $module,
                                'view' => $view,
                            ),
                        )
                    );
            } else {
                throw new AeoException(
                    array(
                        'name' => 'ViewNotFound',
                        'detail' => 'view class not found',
                        'runtime' => array(
                            'module' => $module,
                            'view' => $view,
                        ),
                    )
                );
            }

            return $obj;
        }

        /* Load app helper */
        public function loadHelper($helper, $module='this')
        {
            if ('this' == $module) {
                global $thisModule;

                $module = $thisModule;
            }

            $helper = $module . '_' . $helper . '_helper';
            $path = A_PREFIX . "module/$module/helper/$helper";
            if (is_file($path . '.php')) {
                self::loadClass($path);

                if (function_exists($helper))
                    return $helper;
                else
                    throw new AeoException(
                        array(
                            'name' => 'HelperNotDefined',
                            'detail' => 'helper not defined',
                            'runtime' => array('module' => $module, 'helper' => $helper)
                        )
                    );



            } else {
                throw new AeoException(
                    array(
                        'name' => 'HelperNotFound',
                        'detail' => 'helper not found',
                        'runtime' => array('module' => $module, 'helper' => $helper)
                    )
                );
            }
        }
    }

?>
