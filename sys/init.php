<?php
  /**
   * Aeolus init script
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   *
   */

  # Use Xdebug for debugging
  if( APP_DEBUG ){
    if( function_exists( 'xdebug_start_trace' ) ){
	  # Xdebug enabled
	  xdebug_start_trace('aeolus_trace',4);
	}
    # Show all errors
	error_reporting( E_ALL );
  }else{
    # Turn off error reporting
	error_reporting(0);
  }

  # Start session
  session_start();

  # Add 'opt' and 'sys' directory to the include_path
  $path = AEOLUS_HOME.'/opt/'.PATH_SEPARATOR;
  $path .= AEOLUS_HOME.'/sys/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);

  # Apache mod_rewrite detecting
  if(isset($_GET['rewrite']) && 1 == $_GET['rewrite'] ){
    define('AEOLUS_CAN_REWRITE',true);
  }else{
    define('AEOLUS_CAN_REWRITE',false);
  }

  # Base URL 
  $base = rtrim(APP_SUB,'/\\');
  if( !AEOLUS_CAN_REWRITE ){
    $base .= '/index.php';
  }
  define('APP_BASE',$base);
  
  # Load factory class
  require 'kernel/AeolusFactory.php';

?>
