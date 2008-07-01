<?php
  /*
   * Aeolus front controller 
   */

  define('A_PREFIX', dirname(__FILE__).'/');

  require 'etc/app.php';
  if (!APP_ENABLED) {
    require 'pub/error/aeolus_na.html';
    die();
  }

  require 'sys/bootstrap.php';
  require 'kernel/AeoFront.php';

  $front = new AeoFront();
  $front->run();
?>
