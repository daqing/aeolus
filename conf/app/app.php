<?php
  /**
   * Application-specific configuration
   *
   */

  # Debug control
  if(! defined('AEOLUS_DEBUG') ){
    define('AEOLUS_DEBUG',true);
  }

  # The subdirectory where Aeolus's installed
  if( !defined('AEOLUS_SUBDIR') ){
    define('AEOLUS_SUBDIR','/git-repo/aeolus');
  }
  
  # Default language to use
  if( !defined('LANG') ){
    define('LANG','zh_CN');
  }
  
  # Default theme
  if( !defined('THEME') ){
    define('THEME','default');
  }
?>
