<?php
  /**
   * Init settings
   *
   */

  # Setup the root directory of Aeolus framework
  define('AEOLUS_HOME',dirname(dirname(__FILE__)));
  
  # add AEOLUS_HOME.'/lib/' to the include_path
  $path = AEOLUS_HOME.'/lib/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);

?>
