<?php
 /**
  * Aeolus front controller 
  *
  * @author Kinch Zhang <kinch.zhang@gmail.com>
  */
 
 define('AEOLUS_HOME', dirname(__FILE__));
 define('AEOLUS_STARTED', true);

 # Load configuration 
 require( 'etc/app.php' );
 
 if(! APP_ENABLED){
   require( 'pub/error/aeolus_na.html' );
   die();
 }

 # Bootstrap
 require( 'sys/bootstrap.php' );

 # Run 
 require( 'kernel/AFront.php' );

 $front = new AFront();
 $front->run();
 
?>
