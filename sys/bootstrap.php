<?php
  /**
   * Aeolus bootstrap script
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  # Error reporting
  if( APP_DEBUG ){
    # Show all errors
	error_reporting( E_ALL );
  }else{
    # Turn off error reporting
	error_reporting(0);
  }
  
  if(! isset($GLOBALS['session']['started']) ){
    # Start session
    session_start();
	$GLOBALS['session']['started'] = true;
  }

  # Add 'opt' and 'sys' directories to the include_path
  $path = AEOLUS_HOME.'/opt/'.PATH_SEPARATOR;
  $path .= AEOLUS_HOME.'/sys/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);

  # Application URL prefix 
  $rewrite = isset($_GET['rewrite']) && 1 == $_GET['rewrite'];
  $prefix = $rewrite ? APP_SUB : APP_SUB.'/index.php';
  define('APP_PREFIX',$prefix);
?>
