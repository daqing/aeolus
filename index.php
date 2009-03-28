<?php

    /*
     * This is the frontend for Aeolus system.
     *
     */

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

        global $thisModule;

        if (! APP_ENABLED) {
            $thisModule = 'exception';

            $action = Aeolus::loadController('app_not_running', 'exception');
            $action();
        }

        // Load front controller
        require 'kernel/AeoFront.php';

        $front = new AeoFront();

        $front->run();
    } catch (AeoException $e) {
        die(implode('<br/>', $e->get_trace()));
        //$e->show(APP_DEBUG);
    }
?>
