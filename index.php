<?php

  /*
   * This is the frontend for Aeolus system.
   *
   */

  define('A_PREFIX', dirname(__FILE__).'/');

  require 'etc/app.php';
  if (!APP_ENABLED) {
    require 'pub/error/aeolus_na.html';
    die();
  }

  // Bootstrap
  require 'sys/bootstrap.php';

  // Load front controller
  require 'kernel/AeoFront.php';

  $front = new AeoFront();

  $front->run();
?>
