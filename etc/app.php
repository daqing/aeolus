<?php
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
  # Type '/' if you installed Aeolus under DOCUMENT_ROOT,
  # if else, *don't* forget the slashes
  define('SUB_DIR','/aeolus/');
  
  # Theme
  define('APP_STYLE','default');

  # Template
  define('APP_TPL', 'aeolus');
?>
