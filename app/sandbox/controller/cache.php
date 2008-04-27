<?php
  /**
   * Cache controller in 'sandbox' module
   *
   */

  function index()
  {
	$cache = aeolus_kernel_factory('cache/FileCache.php');
	$cache->debug();

    echo 'Hello,world! [From cache controller in \'sandbox\' module]';
  }
?>
