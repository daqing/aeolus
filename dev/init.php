<?php
  /**
   * Init settings
   *
   */

  # Setup the root directory of Aeolus framework
  define('AEOLUS_ROOT',dirname(dirname(__FILE__)));
  
  # add AEOLUS_ROOT.'/lib/' to the include_path
  $path = AEOLUS_ROOT.'/lib/'.PATH_SEPARATOR;
  $path .= ini_get('include_path');
  ini_set('include_path',$path);

?>
