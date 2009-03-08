<?php

    /*
     * This is the frontend for Aeolus system.
     *
     */

    define('A_PREFIX', dirname(__FILE__).'/');

    // Load application configuration
    require 'config/system/app.php';

    if (!APP_ENABLED) {
      require 'public/error/aeolus_na.html';
      die();
    }

    // Bootstrap
    require 'system/bootstrap.php';

    // Load front controller
    require 'kernel/AeoFront.php';


    // Load Aeolus exception class
    require 'system/AeoException.php';

    try {
        $front = new AeoFront();

        $front->run();
    } catch (AeoException $e) {
        // TODO: display user-friendly error page
        die($e->getMessage());
    }

?>
