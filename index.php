<?php
 /**
  * Front controller 
  *
  * @author Qingcheng Zhang <kinch.zhang@gmail.com>
  *
  */

 define('AEOLUS_HOME',dirname(__FILE__));

 # Load configuration 
 require( 'etc/app.php' );
 
 # Init.
 require( 'sys/init.php' );

 # Start the application
 require( 'kernel/AeolusFront.php' );

 $front = new AeolusFront();
 $front->run();

?>
