<?php
 /**
  * Front controller 
  *
  * @author Qingcheng Zhang <kinch.zhang@gmail.com>
  *
  */

 define('AEOLUS_HOME',dirname(__FILE__));

 # Load configuration file
 require( 'etc/app.php' );

 # Init
 require( 'sys/init.php' );

 # load front controller
 echo 'init.';

 #Show debug trace if xdebug's enabled
 if( APP_DEBUG && function_exists( 'xdebug_start_trace' ) ){
	  echo '<h3>[XDEBUG INFO]</h3>';
	  echo '<pre>';
	  echo file_get_contents(xdebug_get_tracefile_name());
	  echo '</pre>';
 }
?>
