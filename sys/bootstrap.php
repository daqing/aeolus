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
  $path .= A_PREFIX.'sys/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);

  $rewrite = isset($_GET['rewrite']) && 1 == $_GET['rewrite'];
  $prefix = $rewrite ? SUB_DIR : SUB_DIR . 'index.php/';

  define('URL_BASE', $prefix);
?>
