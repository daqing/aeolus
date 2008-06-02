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
  # If you installed Aeolus under 'DOCUMENT_ROOT/foo', type '/foo'(no trailing slash)
  # If else, leave this blank
  define('APP_SUB','/aeolus');
  
  # Theme
  define('APP_STYLE','default');

  # Template
  define('APP_TPL', 'aeolus');
?>
