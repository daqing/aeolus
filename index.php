<?php
 /**
  * Front controller 
  *
  * @author Qingcheng Zhang <kinch.zhang@gmail.com>
  *
  */

 define('AEOLUS_HOME',dirname(__FILE__));

 # Load configuration 
 require( './etc/app.php' );
 
 # Init.
 require( './sys/init.php' );

 # Start the application
 require( 'kernel/AeolusFront.php' );

 $front = new AeolusFront();
 $front->start();

 #Show debug trace if xdebug's enabled
 if( APP_DEBUG && function_exists( 'xdebug_start_trace' ) ){
   echo '------------------------'; 
   var_dump(ini_get('include_path'));
   echo '------------------------' ;  
   echo '<h3>[XDEBUG INFO]</h3>';
   echo '<pre>';
   echo file_get_contents(xdebug_get_tracefile_name());
   echo '</pre>';
 }
?>
