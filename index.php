<?php

    /*
     * This is the frontend for Aeolus system.
     *
     */

    global $env;

    $env['cur_module'] = 'index';
    $env['controller'] = 'index';

    try {
        define('A_PREFIX', dirname(__FILE__) . '/');

        // Load application configuration
        require 'config/system/app.php';

        // Bootstrap
        require 'system/bootstrap.php';

        // Load Aeolus factory class
        require 'Aeolus.php';

        // Load Aeolus Exception class
        require 'AeoException.php';

        if (! APP_ENABLED) {
            throw new AeoException(
                array(
                    'name' => 'APPNotRunning',
                    'detail' => 'app not running',
                    'runtime' => array(),
                )
            );
        }

        // Load front controller
        require 'kernel/AeoFront.php';

        $front = new AeoFront();

        $front->run();
    } catch (AeoException $e) {
        // make sure no exception will be thrown here
        $e->show(APP_DEBUG);
    }
?>
