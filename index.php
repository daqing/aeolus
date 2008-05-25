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
 
 # Bootstrap
 require( 'sys/bootstrap.php' );

 # Run 
 require( 'kernel/AeolusFront.php' );

 $front = new AeolusFront();
 $front->run();

?>
