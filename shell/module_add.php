#! /usr/bin/php
<?php
  /**
   * Add a module
   *
   */

  define('AEOLUS_ROOT',rtrim(dirname(dirname(__FILE__))));
  
  if( 2 > $argc ){
    echo '[Usage] module.php modules';
	var_dump($argv);
  }
?>
