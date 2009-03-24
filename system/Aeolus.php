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

            if (file_exists($path)) {
                require $path;

                return $action;
            } else {
                throw new AeoException('controller_not_found');
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
            if (file_exists($path . '.php')) {
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
            if (file_exists($path . '.php')) {
                self::loadClass('kernel/AeoView');
                self::loadClass($path);

                if (class_exists($viewClass))
                    $obj = new $viewClass();
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

            $path = A_PREFIX . "module/$module/helper/$helper";
            if (file_exists($path . '.php'))
                self::loadClass($path);
        }
    }

?>
