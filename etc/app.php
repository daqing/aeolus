<?php if(! defined('AEOLUS_STARTED')){ die('<h3>BAD REQUEST</h3>');}
  /**
   * Application-specific configuration
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

  # Availability control
  define('APP_ENABLED', true);

  # Debug control
  define('APP_DEBUG',true);

  # The subdirectory where Aeolus's installed
  # NOTE:
  # If you installed Aeolus under ROOT, type '/' here
  # Otherwise, *don't* forget the leading slash
  define('APP_SUB','/aeolus');
  
  # Theme
  define('APP_STYLE','default');

  # Template
  define('APP_TPL', 'aeolus');
?>
