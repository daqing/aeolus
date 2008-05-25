<?php if( basename(__FILE__) == basename($_SERVER['REQUEST_URI'])){
        die('<h3>BAD REQUEST</h3>');
	  }
  /**
   * Application-specific configuration
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  # Debug control
  define('APP_DEBUG',true);

  # The subdirectory where Aeolus's installed
  # Don't forget the leading slash
  define('APP_SUB','/aeolus');
  
  # Default theme
  define('APP_THEME','default');
?>
