<?php
  
    /*
     * This is the frontend for Aeolus system.
     *
     */
  
    define('A_PREFIX', dirname(__FILE__).'/');
  
    // Load application configuration
    require 'config/app.php';

    if (!APP_ENABLED) {
      require 'pub/error/aeolus_na.html';
      die();
    }
  
    // Bootstrap
    require 'sys/bootstrap.php';
  
    // Load front controller
    require 'kernel/AeoFront.php';
  
  
    // Load Aeolus exception class
    require 'sys/AeoException.php';
  
    try {
        $front = new AeoFront();
  
        $front->run();
    } catch (AeoException $e) {
        // TODO: display user-friendly error page
        die($e->getMessage());
    }

?>
