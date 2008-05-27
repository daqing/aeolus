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

  # Application URL prefix 
  $prefix = rtrim(APP_SUB,'/\\');
  $rewrite = isset($_GET['rewrite']) && 1 == $_GET['rewrite'];
  
  if(! $rewrite ){
    # Apache mod_rewrite disabled
    $prefix .= '/index.php';
  }
  define('APP_PREFIX',$prefix);
  
  # Application started
  define('APP_STARTED',true);
?>
