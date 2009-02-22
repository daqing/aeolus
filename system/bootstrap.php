<?php
  /*
   * Aeolus bootstrap script
   */

  (APP_DEBUG) ? error_reporting(E_ALL) : error_reporting(0);
  
  if (!isset($GLOBALS['session_started'])) {
    session_start();
	$GLOBALS['session_started'] = true;
  }

  $path = A_PREFIX.'opt/'.PATH_SEPARATOR;
  $path .= A_PREFIX.'system/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);
?>
