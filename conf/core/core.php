<?php
  /**
   * Aeolus configuration file
   *
   */

  # Aeolus root directory which contains the 'index.php' file
  if(! defined('AEOLUS_ROOT') ){
    define('AEOLUS_ROOT',dirname(dirname(dirname(__FILE__))));
  }

  # Add 'lib' directory to include_path
  if(! defined('AEOLUS_INCLUDED') ){
    ini_set('include_path',AEOLUS_ROOT.'/lib/'.ini_get('include_path'));
	define('AEOLUS_INCLUDED',true);
  }

  # Use Xdebug for debugging
  if( AEOLUS_DEBUG ){
    if( function_exists( 'xdebug_start_trace' ) ){
	  # Xdebug enabled
	  xdebug_start_trace('aeolus_debug',4);
	}

    # Show all errors
	error_reporting( E_ALL );
  }else{
    # Turn off error reporting
	error_reporting(0);
  }

  # Start session
  session_start();

  # Apache mod_rewrite detecting
  if( !defined('AEOLUS_CAN_REWRITE') ){
    if(isset($_GET['rewrite']) && 1 == $_GET['rewrite'] ){
	  define('AEOLUS_CAN_REWRITE',true);
	}else{
	  define('AEOLUS_CAN_REWRITE',false);
    }
  }

  # Output base 
  if( !defined('AEOLUS_OUTPUT') ){
    $base = rtrim(AEOLUS_SUBDIR,'/\\');
	if( !AEOLUS_CAN_REWRITE ){
	  $base .= '/index.php';
	}

	define('AEOLUS_OUTPUT',$base);
  }

?>
