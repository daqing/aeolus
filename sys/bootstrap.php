<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
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
  
  if(! isset($GLOBALS['session']['started'])){
    # Start session
    session_start();
	$GLOBALS['session']['started'] = true;
  }

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
?>
