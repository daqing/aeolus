<?php

  /*
   * Aeolus bootstrap script
   */

  (APP_DEBUG) ? error_reporting(E_ALL) : error_reporting(0);

  session_start();
  
  $path = A_PREFIX.'plugin/'.PATH_SEPARATOR;
  $path .= A_PREFIX.'system/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);
?>
