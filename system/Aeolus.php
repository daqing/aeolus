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

        /* Load module controller */
        public function loadController($controller, $module='this')
        {
            if ('this' == $module) {
                global $thisModule;

                $module = $thisModule;
            }

            $path = A_PREFIX . "module/$module/controller/{$module}_{$controller}.php";

            if (APP_DEBUG)
                clearstatcache();

            if (file_exists($path)) {
                require $path;
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
            $path = A_PREFIX . "module/$module/model/$model";
            if (file_exists($path . '.php')) {
                self::loadClass('kernel/AeoModel');
                self::loadClass($path);

                if (class_exists($model))
                    $obj = new $model();
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
            $path = A_PREFIX . "module/$module/view/$view";
            if (file_exists($path . '.php')) {
                self::loadClass('kernel/AeoView');
                self::loadClass($path);

                if (class_exists($view))
                    $obj = new $view();
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
